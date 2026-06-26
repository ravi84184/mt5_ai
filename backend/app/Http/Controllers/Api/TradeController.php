<?php

namespace App\Http\Controllers\Api;

use App\Enums\SignalStatus;
use App\Http\Concerns\AuthorizesMt5Account;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    use AuthorizesMt5Account;

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['nullable', 'integer'],
            'ticket' => ['required', 'integer'],
            'status' => ['required', 'string'],
            'profit' => ['nullable', 'numeric'],
            'close_price' => ['nullable', 'numeric'],
            'symbol' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'lot' => ['nullable', 'numeric'],
            'entry_price' => ['nullable', 'numeric'],
        ]);

        $trade = Trade::where('ticket', $validated['ticket'])->first();

        if (! $trade && ! empty($validated['account'])) {
            $account = Account::where('mt5_login', $validated['account'])->first();
            if ($account) {
                $trade = Trade::create([
                    'ticket' => $validated['ticket'],
                    'account_id' => $account->id,
                    'symbol' => $validated['symbol'] ?? 'UNKNOWN',
                    'type' => $validated['type'] ?? 'BUY',
                    'lot' => $validated['lot'] ?? 0,
                    'entry_price' => $validated['entry_price'] ?? 0,
                    'status' => strtoupper($validated['status']),
                ]);
            }
        }

        if (! $trade) {
            return response()->json(['error' => 'Trade not found'], 404);
        }

        if ($response = $this->denyIfWrongAccountId($request, $trade->account_id)) {
            return $response;
        }

        $trade->update([
            'status' => strtoupper($validated['status']),
            'profit' => $validated['profit'] ?? $trade->profit,
            'close_price' => $validated['close_price'] ?? $trade->close_price,
        ]);

        if ($trade->signal_id && strtoupper($validated['status']) === 'CLOSED') {
            $trade->signal?->update(['status' => SignalStatus::Closed]);
        }

        if (isset($validated['profit'])) {
            $this->updateDailyPnl($trade->account, (float) $validated['profit']);
        }

        return response()->json(['status' => 'ok']);
    }

    private function updateDailyPnl(Account $account, float $profitDelta): void
    {
        $today = Carbon::today();

        if ($account->pnl_date?->isSameDay($today) !== true) {
            $account->update([
                'pnl_date' => $today,
                'daily_pnl' => $profitDelta,
            ]);

            return;
        }

        $account->increment('daily_pnl', $profitDelta);
    }
}
