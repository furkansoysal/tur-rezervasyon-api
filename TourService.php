<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class TourService
{
    public function __construct(private PDO $db, private \DateTimeImmutable $now) {}

    /** @param array{city?:string,from?:string,to?:string} $filters @return list<array<string,mixed>> */
    public function list(array $filters): array
    {
        $sql = 'SELECT * FROM tours WHERE 1=1';
        $args = [];
        if (!empty($filters['city'])) {
            $sql .= ' AND city = :city COLLATE NOCASE';
            $args['city'] = $filters['city'];
        }
        $tours = $this->db->prepare($sql . ' ORDER BY title');
        $tours->execute($args);
        $rows = $tours->fetchAll();

        $out = [];
        foreach ($rows as $tour) {
            $deps = $this->departures((int) $tour['id'], $filters['from'] ?? null, $filters['to'] ?? null);
            // Tarih filtresi verilmişse o aralıkta kalkışı olmayan turu listeleme
            if ((isset($filters['from']) || isset($filters['to'])) && $deps === []) {
                continue;
            }
            $out[] = $this->present($tour, $deps);
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function findBySlug(string $slug): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tours WHERE slug = ?');
        $stmt->execute([$slug]);
        $tour = $stmt->fetch();
        if ($tour === false) {
            throw new HttpException(404, 'Tur bulunamadı.');
        }
        return $this->present($tour, $this->departures((int) $tour['id']));
    }

    /** @return list<array{id:int,date:string,capacity:int,remaining:int}> */
    private function departures(int $tourId, ?string $from = null, ?string $to = null): array
    {
        $from = max($from ?? '', $this->now->format('Y-m-d'));
        $sql = "SELECT d.id, d.date, d.capacity,
                       d.capacity - COALESCE((SELECT SUM(r.adults + r.children) FROM reservations r
                                              WHERE r.departure_id = d.id AND r.status = 'confirmed'), 0) AS remaining
                FROM departures d WHERE d.tour_id = :t AND d.date >= :from";
        $args = ['t' => $tourId, 'from' => $from];
        if ($to !== null) {
            $sql .= ' AND d.date <= :to';
            $args['to'] = $to;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY d.date');
        $stmt->execute($args);
        return array_map(
            static fn (array $d): array => ['id' => (int) $d['id'], 'date' => $d['date'], 'capacity' => (int) $d['capacity'], 'remaining' => (int) $d['remaining']],
            $stmt->fetchAll(),
        );
    }

    /** @param array<string,mixed> $tour @param list<array<string,mixed>> $deps @return array<string,mixed> */
    private function present(array $tour, array $deps): array
    {
        return [
            'slug' => $tour['slug'],
            'title' => $tour['title'],
            'city' => $tour['city'],
            'description' => $tour['description'],
            'duration_days' => (int) $tour['duration_days'],
            'adult_price' => (int) $tour['adult_price'],
            'child_price' => (int) $tour['child_price'],
            'departures' => array_map(
                static fn (array $d): array => ['date' => $d['date'], 'remaining' => max(0, $d['remaining']), 'sold_out' => $d['remaining'] <= 0],
                $deps,
            ),
        ];
    }
}
