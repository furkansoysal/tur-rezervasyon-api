<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{string,string,callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (preg_match($regex, $request->path, $m) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $request->method) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request, $params);
        }
        throw $pathMatched
            ? new HttpException(405, 'Bu adres için bu HTTP metodu desteklenmiyor.')
            : new HttpException(404, 'Adres bulunamadı.');
    }
}
