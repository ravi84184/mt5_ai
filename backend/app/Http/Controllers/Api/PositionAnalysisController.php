<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPositionAnalysisJob;
use App\Models\Account;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionAnalysisController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['nullable', 'array'],
            'account.login' => ['required_with:account', 'integer'],
            'ticket' => ['required', 'integer'],
            'position' => ['required', 'array'],
            'position.symbol' => ['required', 'string'],
            'position.type' => ['required', 'string'],
            'market_data' => ['nullable', 'array'],
        ]);

        if (! empty($validated['account'])) {
            $account = Account::findOrCreateFromMt5($validated['account']);
        } else {
            $trade = Trade::where('ticket', $validated['ticket'])->first();
            if (! $trade) {
                return response()->json(['error' => 'Trade not found for ticket'], 404);
            }
            $account = $trade->account;
        }

        ProcessPositionAnalysisJob::dispatch($account->id, $validated);

        return response()->json(['status' => 'accepted']);
    }
}
