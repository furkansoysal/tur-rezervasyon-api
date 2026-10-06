<?php
declare(strict_types=1);

namespace App;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Services\ReservationService;
use App\Services\TourService;
use PDO;

final class App
{
    private Router $router;

    public function __construct(PDO $db, \DateTimeImmutable $now)
    {
        $tours = new TourService($db, $now);
        $reservations = new ReservationService($db, $now);
        $r = $this->router = new Router();

        $r->add('GET', '/api/health', fn () => new Response(200, ['status' => 'ok']));

        $r->add('GET', '/api/tours', function (Request $req) use ($tours): Response {
            $f = Validator::validate($req->query, ['city' => 'string|max:60', 'from' => 'date', 'to' => 'date']);
            return new Response(200, ['data' => $tours->list($f)]);
        });

        $r->add('GET', '/api/tours/{slug}', fn (Request $req, array $p) => new Response(200, ['data' => $tours->findBySlug($p['slug'])]));

        $r->add('POST', '/api/reservations', function (Request $req) use ($reservations): Response {
            $data = Validator::validate($req->body + ['children' => 0], [
                'tour' => 'required|string|max:80',
                'date' => 'required|date',
                'adults' => 'required|int|min:1|max:10',
                'children' => 'int|min:0|max:10',
                'name' => 'required|string|max:80',
                'email' => 'required|email|max:120',
                'phone' => 'required|phone',
            ]);
            $data += ['children' => 0];
            return new Response(201, ['data' => $reservations->create($data)]);
        });

        $r->add('GET', '/api/reservations/{code}', fn (Request $req, array $p) => new Response(200, ['data' => $reservations->find($p['code'])]));
        $r->add('POST', '/api/reservations/{code}/cancel', fn (Request $req, array $p) => new Response(200, ['data' => $reservations->cancel($p['code'])]));
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            $body = ['error' => $e->getMessage()];
            if ($e->errors !== []) {
                $body['fields'] = $e->errors;
            }
            return new Response($e->status, $body);
        } catch (\Throwable $e) {
            error_log((string) $e);
            return new Response(500, ['error' => 'Beklenmeyen bir hata oluştu.']);
        }
    }
}
