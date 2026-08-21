<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowFilamentAssetCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_starts_with($request->path(), 'js/filament/')) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');

        return $response;
    }
}
