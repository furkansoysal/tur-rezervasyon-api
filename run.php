<?php
declare(strict_types=1);

// Çalıştırma: php tests/run.php   (geçici bir SQLite dosyası kullanır, gerçek veriye dokunmaz)
require dirname(__DIR__) . '/src/autoload.php';

use App\App;
use App\Core\Database;
use App\Core\Request;

date_default_timezone_set('Europe/Istanbul');
$file = sys_get_temp_dir() . '/rez-test-' . bin2hex(random_bytes(4)) . '.sqlite';
$db = Database::connect($file);
Database::migrate($db);

// Sabit "şimdi": testler tarihe bağlı olmasın
$now = new DateTimeImmutable('2026-06-01 10:00:00');
$app = new App($db, $now);

$db->exec("INSERT INTO tours (slug,title,city,description,duration_days,adult_price,child_price) VALUES
  ('kapadokya','Kapadokya','Nevşehir','x',2,3900,1950),
  ('bursa','Bursa','Bursa','x',1,1250,650)");
$db->exec("INSERT INTO departures (tour_id,date,capacity) VALUES
  (1,'2026-06-02',12), (1,'2026-06-20',12), (1,'2026-05-20',12), (2,'2026-07-01',4)");

$passed = 0; $failed = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . ($ok ? '' : "  -> $detail") . "\n";
}
function call(App $app, string $m, string $path, array $body = [], array $query = []): array {
    $r = $app->handle(new Request($m, $path, $query, $body));
    return [$r->status, $r->data];
}
$valid = ['tour' => 'kapadokya', 'date' => '2026-06-20', 'adults' => 2, 'children' => 1,
          'name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com', 'phone' => '0532 123 45 67'];

echo "Turlar\n";
[$s, $d] = call($app, 'GET', '/api/tours');
check('liste 200 ve 2 tur', $s === 200 && count($d['data']) === 2, json_encode($d));
[$s, $d] = call($app, 'GET', '/api/tours', [], ['city' => 'bursa']);
check('şehir filtresi (büyük/küçük harf duyarsız)', $s === 200 && count($d['data']) === 1 && $d['data'][0]['slug'] === 'bursa');
[$s, $d] = call($app, 'GET', '/api/tours/kapadokya');
$deps = array_column($d['data']['departures'], 'date');
check('geçmiş kalkışlar listelenmez', $deps === ['2026-06-02', '2026-06-20'], json_encode($deps));
[$s] = call($app, 'GET', '/api/tours/yok');
check('olmayan tur 404', $s === 404);
[$s, $d] = call($app, 'GET', '/api/tours', [], ['from' => '2026-06-30']);
check('tarih filtresi: aralıkta kalkışı olmayan tur çıkmaz', $s === 200 && count($d['data']) === 1 && $d['data'][0]['slug'] === 'bursa', json_encode($d));
[$s] = call($app, 'GET', '/api/tours', [], ['from' => '31-12-2026']);
check('hatalı tarih biçimi 422', $s === 422);

echo "Rezervasyon oluşturma\n";
[$s, $d] = call($app, 'POST', '/api/reservations', $valid);
$code = $d['data']['code'] ?? '';
check('201 ve rezervasyon kodu', $s === 201 && str_starts_with($code, 'KT-'), json_encode($d));
check('fiyat: 2×3900 + 1×1950 = 9750', ($d['data']['total_price'] ?? 0) === 9750, json_encode($d));
[$s, $d] = call($app, 'GET', '/api/tours/kapadokya');
$rem = $d['data']['departures'][1]['remaining'];
check('kontenjan 12 → 9', $rem === 9, (string) $rem);
[$s, $d] = call($app, 'GET', '/api/reservations/' . strtolower($code));
check('kod küçük harfle de bulunur', $s === 200 && $d['data']['code'] === $code);

echo "Doğrulama\n";
[$s, $d] = call($app, 'POST', '/api/reservations', []);
check('boş gövde 422 + alan hataları', $s === 422 && isset($d['fields']['tour'], $d['fields']['email']), json_encode($d));
[$s, $d] = call($app, 'POST', '/api/reservations', ['email' => 'abc'] + $valid);
check('geçersiz e-posta 422', $s === 422 && isset($d['fields']['email']));
[$s, $d] = call($app, 'POST', '/api/reservations', ['adults' => 0] + $valid);
check('0 yetişkin 422', $s === 422 && isset($d['fields']['adults']));
[$s, $d] = call($app, 'POST', '/api/reservations', ['name' => '12345'] + $valid);
check('sayısal görünen isim uzunluk kuralına takılmaz', $s === 201, json_encode($d));
[$s] = call($app, 'POST', '/api/reservations', ['date' => '2026-06-21'] + $valid);
check('kalkışı olmayan tarih 404', $s === 404);
[$s, $d] = call($app, 'POST', '/api/reservations', ['date' => '2026-05-20'] + $valid);
check('geçmiş kalkış 422', $s === 422, json_encode($d));

echo "Kontenjan\n";
[$s, $d] = call($app, 'POST', '/api/reservations', ['tour' => 'bursa', 'date' => '2026-07-01', 'adults' => 4, 'children' => 0] + $valid);
$full = $d['data']['code'] ?? '';
check('son koltuklar alınır (4/4)', $s === 201);
[$s, $d] = call($app, 'POST', '/api/reservations', ['tour' => 'bursa', 'date' => '2026-07-01', 'adults' => 1, 'children' => 0] + $valid);
check('dolu kalkışa 409', $s === 409, json_encode($d));
[$s] = call($app, 'POST', '/api/reservations/' . $full . '/cancel');
check('iptal 200', $s === 200);
[$s, $d] = call($app, 'POST', '/api/reservations', ['tour' => 'bursa', 'date' => '2026-07-01', 'adults' => 4, 'children' => 0] + $valid);
check('iptalden sonra koltuklar tekrar satılır', $s === 201, json_encode($d));
[$s] = call($app, 'POST', '/api/reservations/' . $full . '/cancel');
check('aynı rezervasyon ikinci kez iptal edilemez (409)', $s === 409);

echo "İptal kuralı\n";
[$s, $d] = call($app, 'POST', '/api/reservations', ['date' => '2026-06-02', 'adults' => 1, 'children' => 0] + $valid);
$soon = $d['data']['code'] ?? '';
[$s, $d] = call($app, 'POST', '/api/reservations/' . $soon . '/cancel');
check('kalkışa 48 saatten az kala iptal 409', $s === 409, json_encode($d));
[$s] = call($app, 'POST', '/api/reservations/KT-YOKYOK/cancel');
check('olmayan rezervasyon 404', $s === 404);

echo "Yönlendirme\n";
[$s] = call($app, 'GET', '/api/yok');
check('bilinmeyen adres 404', $s === 404);
[$s] = call($app, 'DELETE', '/api/tours');
check('desteklenmeyen metod 405', $s === 405);
[$s, $d] = call($app, 'GET', '/api/health');
check('health 200', $s === 200 && $d['status'] === 'ok');

@unlink($file);
echo "\n$passed geçti, $failed başarısız\n";
exit($failed === 0 ? 0 : 1);
