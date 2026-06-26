<?php

namespace App\Http\Concerns;

use App\Models\Account;
use App\Support\Mt5ApiAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AuthorizesMt5Account
{
    protected function boundMt5Account(Request $request): ?Account
    {
        return $request->attributes->get('mt5_account');
    }

    protected function denyIfWrongAccount(Request $request, Account $account): ?JsonResponse
    {
        $auth = app(Mt5ApiAuth::class);

        if (! $auth->ownsAccount($this->boundMt5Account($request), $account)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return null;
    }

    protected function denyIfWrongAccountId(Request $request, int $accountId): ?JsonResponse
    {
        $bound = $this->boundMt5Account($request);
        if ($bound === null) {
            return null;
        }

        if ($bound->id !== $accountId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return null;
    }
}
