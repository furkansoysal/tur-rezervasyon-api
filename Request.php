<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $body */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $body = [];
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new HttpException(400, 'Geçersiz JSON gövdesi.');
            }
            $body = $decoded;
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/', $_GET, $body);
    }
}
