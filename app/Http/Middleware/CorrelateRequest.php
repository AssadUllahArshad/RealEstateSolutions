<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelateRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
