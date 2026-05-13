<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => '权限不足'], 403);
            }
            abort(403, '权限不足');
        }

        if ($user->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json(['message' => '账号已被禁用'], 403);
            }
            abort(403, '账号已被禁用');
        }

        return $next($request);
    }
}
