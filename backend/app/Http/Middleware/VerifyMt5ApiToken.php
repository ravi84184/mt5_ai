<?php

namespace App\Http\Middleware;

use App\Support\Mt5ApiAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMt5ApiToken
{
    public function __construct(private Mt5ApiAuth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = $request->header('X-API-TOKEN');
        $globalToken = config('trading.api_token');

        if ($providedToken === null || $providedToken === '') {
            if ($globalToken) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);
        }

        $boundAccount = $this->auth->authenticate($providedToken);

        if (! $this->auth->isValid($providedToken)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($boundAccount) {
            $request->attributes->set('mt5_account', $boundAccount);

            $requestedLogin = $this->auth->extractMt5Login($request);
            if ($requestedLogin !== null && ! $this->auth->accountMatches($boundAccount, $requestedLogin)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
