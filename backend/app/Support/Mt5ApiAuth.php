<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Http\Request;

class Mt5ApiAuth
{
    public function authenticate(?string $providedToken): ?Account
    {
        if ($providedToken === null || $providedToken === '') {
            return null;
        }

        $globalToken = config('trading.api_token');

        if ($globalToken && hash_equals($globalToken, $providedToken)) {
            return null;
        }

        return Account::findByApiToken($providedToken);
    }

    public function isValid(?string $providedToken): bool
    {
        if ($providedToken === null || $providedToken === '') {
            return config('trading.api_token') === null || config('trading.api_token') === '';
        }

        $globalToken = config('trading.api_token');

        if ($globalToken && hash_equals($globalToken, $providedToken)) {
            return true;
        }

        return Account::findByApiToken($providedToken) !== null;
    }

    public function extractMt5Login(Request $request): ?int
    {
        $account = $request->query('account');
        if ($account !== null && $account !== '') {
            return (int) $account;
        }

        $bodyAccount = $request->input('account');
        if (is_array($bodyAccount) && isset($bodyAccount['login'])) {
            return (int) $bodyAccount['login'];
        }

        if (is_numeric($bodyAccount)) {
            return (int) $bodyAccount;
        }

        return null;
    }

    public function accountMatches(?Account $boundAccount, int $mt5Login): bool
    {
        if ($boundAccount === null) {
            return true;
        }

        return (int) $boundAccount->mt5_login === $mt5Login;
    }

    public function ownsAccount(?Account $boundAccount, Account $account): bool
    {
        if ($boundAccount === null) {
            return true;
        }

        return $boundAccount->id === $account->id;
    }
}
