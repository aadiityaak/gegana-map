<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (count($roles) === 0) {
            return $next($request);
        }

        if (! in_array((string) ($user->role ?? ''), $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}

