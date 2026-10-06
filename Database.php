<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public static function connect(?string $path = null): PDO
    {
        $path ??= getenv('DB_PATH') ?: dirname(__DIR__, 2) . '/storage/app.sqlite';
        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 3000');
        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
    }
}
