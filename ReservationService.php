<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class ReservationService
{
    /** Kalkışa bu kadar saatten az kaldıysa iptal edilemez. */
    public const CANCEL_CUTOFF_HOURS = 48;

    public function __construct(private PDO $db, private \DateTimeImmutable $now) {}

    /**
     * Kontenjan kontrolü ve kayıt tek işlemde (BEGIN IMMEDIATE) yapılır;
     * böylece aynı anda gelen iki istek son koltuğu birlikte alamaz.
     *
     * @param array<string,mixed> $data @return array<string,mixed>
     */
    public function create(array $data): array
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $dep = $this->db->prepare(
                'SELECT d.id, d.date, d.capacity, t.title, t.adult_price, t.child_price
                 FROM departures d JOIN tours t ON t.id = d.tour_id
                 WHERE t.slug = ? AND d.date = ?'
            );
            $dep->execute([$data['tour'], $data['date']]);
            $departure = $dep->fetch();
            if ($departure === false) {
                throw new HttpException(404, 'Bu tur için seçilen tarihte kalkış yok.');
            }
            if ($departure['date'] < $this->now->format('Y-m-d')) {
                throw new HttpException(422, 'Geçmiş tarihli kalkışa rezervasyon yapılamaz.', ['date' => 'Geçmiş tarih.']);
            }

            $need = $data['adults'] + $data['children'];
            $booked = (int) $this->db->query(
                "SELECT COALESCE(SUM(adults + children), 0) FROM reservations
                 WHERE departure_id = " . (int) $departure['id'] . " AND status = 'confirmed'"
            )->fetchColumn();
            $remaining = (int) $departure['capacity'] - $booked;
            if ($need > $remaining) {
                throw new HttpException(409, "Yeterli kontenjan yok. Kalan koltuk: {$remaining}.");
            }

            $total = $data['adults'] * (int) $departure['adult_price'] + $data['children'] * (int) $departure['child_price'];
            $code = 'KT-' . strtoupper(bin2hex(random_bytes(4)));
            $ins = $this->db->prepare(
                'INSERT INTO reservations (code, departure_id, adults, children, total_price, customer_name, customer_email, customer_phone, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $code, $departure['id'], $data['adults'], $data['children'], $total,
                $data['name'], $data['email'], $data['phone'], $this->now->format('c'),
            ]);
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->exec('ROLLBACK');
            }
            throw $e;
        }
        return $this->find($code);
    }

    /** @return array<string,mixed> */
    public function find(string $code): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, d.date AS departure_date, t.title AS tour_title, t.slug AS tour_slug
             FROM reservations r
             JOIN departures d ON d.id = r.departure_id
             JOIN tours t ON t.id = d.tour_id
             WHERE r.code = ?'
        );
        $stmt->execute([strtoupper($code)]);
        $r = $stmt->fetch();
        if ($r === false) {
            throw new HttpException(404, 'Rezervasyon bulunamadı.');
        }
        return [
            'code' => $r['code'],
            'status' => $r['status'],
            'tour' => ['slug' => $r['tour_slug'], 'title' => $r['tour_title']],
            'departure_date' => $r['departure_date'],
            'adults' => (int) $r['adults'],
            'children' => (int) $r['children'],
            'total_price' => (int) $r['total_price'],
            'customer' => ['name' => $r['customer_name'], 'email' => $r['customer_email'], 'phone' => $r['customer_phone']],
            'created_at' => $r['created_at'],
            'cancelled_at' => $r['cancelled_at'],
        ];
    }

    /** @return array<string,mixed> */
    public function cancel(string $code): array
    {
        $res = $this->find($code);
        if ($res['status'] === 'cancelled') {
            throw new HttpException(409, 'Rezervasyon zaten iptal edilmiş.');
        }
        $departure = new \DateTimeImmutable($res['departure_date'] . ' 00:00:00', $this->now->getTimezone());
        $hoursLeft = ($departure->getTimestamp() - $this->now->getTimestamp()) / 3600;
        if ($hoursLeft < self::CANCEL_CUTOFF_HOURS) {
            throw new HttpException(409, 'Kalkışa ' . self::CANCEL_CUTOFF_HOURS . ' saatten az kaldığı için iptal edilemez.');
        }
        $this->db->prepare("UPDATE reservations SET status = 'cancelled', cancelled_at = ? WHERE code = ?")
            ->execute([$this->now->format('c'), $res['code']]);
        return $this->find($code);
    }
}
