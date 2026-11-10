<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');
        app()->setLocale(in_array($locale, ['en', 'ur'], true) ? $locale : 'en');

        return $next($request);
    }
}
