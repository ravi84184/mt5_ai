<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'integer'],
        ]);

        $account = Account::where('mt5_login', $validated['account'])->first();

        if (! $account) {
            return response()->json([
                'mt5_login' => $validated['account'],
                'symbols' => [],
                'trading_enabled' => false,
                'ai_provider' => config('trading.ai.provider'),
                'min_confidence' => (int) config('trading.risk.min_confidence', 80),
                'max_open_trades' => (int) config('trading.risk.max_open_trades', 3),
                'configured' => false,
            ]);
        }

        return response()->json([
            ...$account->toEaConfig(),
            'configured' => $account->hasSymbolRestrictions(),
        ]);
    }
}
