<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->header('Origin', '');
        $allowed = array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', parse_url(config('app.url'), PHP_URL_HOST) ?: '')), fn($v) => $v !== '');
        $originHost = parse_url($origin, PHP_URL_HOST) ?: '';

        if ($origin && $originHost && (in_array($originHost, $allowed, true) || in_array($origin, $allowed, true))) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');

        return $response;
    }
}
