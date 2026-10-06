<?php
declare(strict_types=1);

// Kullanım: php bin/seed.php   (örnek turlar ve bugünden itibaren kalkış tarihleri ekler)
require dirname(__DIR__) . '/src/autoload.php';

use App\Core\Database;

date_default_timezone_set('Europe/Istanbul');
$db = Database::connect();
Database::migrate($db);

$tours = [
    ['kapadokya-balon-vadiler', 'Kapadokya Vadiler ve Gün Doğumu', 'Nevşehir', 'Göreme, Uçhisar ve Paşabağları rotası; rehber eşliğinde vadi yürüyüşü.', 2, 3900, 1950, 14, 12],
    ['pamukkale-hierapolis', 'Pamukkale ve Hierapolis Günübirlik', 'Denizli', 'Travertenler, antik havuz ve Hierapolis antik kenti gezisi.', 1, 1450, 750, 7, 30],
    ['uludag-bursa-kultur', 'Bursa Kültür ve Uludağ', 'Bursa', 'Ulu Cami, Koza Han, Cumalıkızık ve Uludağ teleferiği.', 1, 1250, 650, 5, 24],
    ['istanbul-bogaz-ve-tarih', 'İstanbul Tarihi Yarımada ve Boğaz', 'İstanbul', 'Sultanahmet turu ve öğleden sonra Boğaz tekne gezisi.', 1, 1100, 550, 3, 20],
];

$db->exec('BEGIN');
foreach ($tours as [$slug, $title, $city, $desc, $days, $adult, $child, $step, $cap]) {
    $db->prepare('INSERT OR IGNORE INTO tours (slug,title,city,description,duration_days,adult_price,child_price) VALUES (?,?,?,?,?,?,?)')
        ->execute([$slug, $title, $city, $desc, $days, $adult, $child]);
    $id = (int) $db->query("SELECT id FROM tours WHERE slug = '$slug'")->fetchColumn();
    for ($i = 1; $i <= 6; $i++) {
        $date = (new DateTimeImmutable('today'))->modify('+' . ($i * $step) . ' days')->format('Y-m-d');
        $db->prepare('INSERT OR IGNORE INTO departures (tour_id,date,capacity) VALUES (?,?,?)')->execute([$id, $date, $cap]);
    }
}
$db->exec('COMMIT');
echo "Örnek veriler eklendi.\n";
