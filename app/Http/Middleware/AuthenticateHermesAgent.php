<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateHermesAgent
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('services.hermes.api_token');

        if (!is_string($token) || trim($token) === '') {
            return response()->json(['message' => 'Server misconfigured: HERMES_API_TOKEN not set.'], 500);
        }

        $bearer = $request->bearerToken();

        if (!is_string($bearer) || !hash_equals($token, $bearer)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
