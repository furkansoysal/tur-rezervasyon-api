<?php
declare(strict_types=1);

// php -S ile çalışırken: statik dosyaları (demo.html vb.) sunucu kendisi versin.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/src/autoload.php';

use App\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

date_default_timezone_set('Europe/Istanbul');

if (($_SERVER['REQUEST_URI'] ?? '/') === '/') {
    header('Location: /demo.html');
    return;
}

try {
    $db = Database::connect();
    Database::migrate($db);
    $app = new App($db, new DateTimeImmutable('now'));
    $app->handle(Request::fromGlobals())->send();
} catch (HttpException $e) {
    (new Response($e->status, ['error' => $e->getMessage()]))->send();
}
