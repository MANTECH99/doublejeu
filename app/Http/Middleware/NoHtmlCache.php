<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoHtmlCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $type = $response->headers->get('Content-Type') ?? '';
        if (str_contains($type, 'text/html') || str_contains($type, 'application/json')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }
}
