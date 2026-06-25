<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMt5ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('trading.api_token');

        if ($token && $request->header('X-API-TOKEN') !== $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
