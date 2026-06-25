<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMarketAnalysisJob;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'array'],
            'account.login' => ['required', 'integer'],
            'account.balance' => ['nullable', 'numeric'],
            'account.equity' => ['nullable', 'numeric'],
            'account.free_margin' => ['nullable', 'numeric'],
            'symbols' => ['required', 'array', 'min:1'],
            'symbols.*.symbol' => ['required', 'string', 'max:32'],
            'symbols.*.timeframe' => ['nullable', 'string', 'max:8'],
            'symbols.*.indicators' => ['nullable', 'array'],
            'symbols.*.candles' => ['nullable', 'array'],
        ]);

        $account = Account::findOrCreateFromMt5($validated['account']);

        ProcessMarketAnalysisJob::dispatch($account->id, $validated);

        return response()->json(['status' => 'accepted']);
    }
}
