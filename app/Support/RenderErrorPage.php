<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RenderErrorPage
{
    public function __invoke(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();
        if ($request->expectsJson() || $request->is('api/*')) {
            return $response;
        }
        $publicStatuses = [403, 404, 419, 429];
        if (! config('app.debug')) {
            $publicStatuses = [...$publicStatuses, 500, 503];
        }
        if (! in_array($status, $publicStatuses, true)) {
            return $response;
        }

        $page = Inertia::render('errors/status', [
            'status' => $status,
            'requestId' => $request->attributes->get('request_id'),
        ])->toResponse($request)->setStatusCode($status);

        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        return $page;
    }
}
