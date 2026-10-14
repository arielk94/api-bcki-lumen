<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $origin = env('CORS_ALLOWED_ORIGIN', '*');

        if ($request->getMethod() === "OPTIONS") {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Methods'     => '*',
                'Access-Control-Allow-Headers'     => '*',
                'Access-Control-Max-Age'           => '86400',
            ]);
        }

        $response = $next($request);

        return $response->withHeaders([
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'     => '*',
            'Access-Control-Allow-Headers'     => '*',
        ]);
    }
}
