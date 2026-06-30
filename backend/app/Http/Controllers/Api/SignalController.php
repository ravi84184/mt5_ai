<?php

namespace App\Http\Controllers\Api;

use App\Enums\SignalStatus;
use App\Http\Concerns\AuthorizesMt5Account;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PositionManagementDecision;
use App\Models\Signal;
use App\Models\Trade;
use App\Models\TradeManagementLog;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignalController extends Controller
{
    use AuthorizesMt5Account;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'integer'],
        ]);

        $account = Account::where('mt5_login', $validated['account'])->first();
        if (! $account) {
            return response()->json(['status' => 'NO_SIGNAL']);
        }

        if ($response = $this->denyIfWrongAccount($request, $account)) {
            return $response;
        }

        if (! $account->isTradingEnabled()) {
            return response()->json(['status' => 'NO_SIGNAL']);
        }

        $signal = Signal::pendingForAccount($account->id)->first();
        if (! $signal) {
            return response()->json(['status' => 'NO_SIGNAL']);
        }

        return response()->json([
            'id' => $signal->id,
            'symbol' => $signal->symbol,
            'action' => $signal->action,
            'confidence' => $signal->confidence,
            'entry_price' => (float) $signal->entry_price,
            'stop_loss' => (float) $signal->stop_loss,
            'take_profit' => (float) $signal->take_profit,
        ]);
    }

    public function executed(Request $request, TelegramNotificationService $telegram): JsonResponse
    {
        $validated = $request->validate([
            'signal_id' => ['required', 'integer', 'exists:signals,id'],
            'ticket' => ['required', 'integer'],
            'status' => ['required', 'string'],
            'symbol' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'lot' => ['nullable', 'numeric'],
            'entry_price' => ['nullable', 'numeric'],
        ]);

        $signal = Signal::findOrFail($validated['signal_id']);

        if ($response = $this->denyIfWrongAccountId($request, $signal->account_id)) {
            return $response;
        }

        $signal->update([
            'status' => SignalStatus::Executed,
            'ticket' => $validated['ticket'],
        ]);

        $trade = Trade::updateOrCreate(
            ['ticket' => $validated['ticket']],
            [
                'signal_id' => $signal->id,
                'account_id' => $signal->account_id,
                'symbol' => $validated['symbol'] ?? $signal->symbol,
                'type' => $validated['type'] ?? $signal->action,
                'lot' => $validated['lot'] ?? 0,
                'entry_price' => $validated['entry_price'] ?? $signal->entry_price,
                'status' => 'OPEN',
            ]
        );

        $telegram->notifyTradeOpened($trade, $signal->account);

        return response()->json(['status' => 'ok']);
    }

    public function management(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'integer'],
            'ticket' => ['nullable', 'integer'],
        ]);

        $account = Account::where('mt5_login', $validated['account'])->first();
        if (! $account) {
            return response()->json(['status' => 'NO_ACTION']);
        }

        if ($response = $this->denyIfWrongAccount($request, $account)) {
            return $response;
        }

        $query = PositionManagementDecision::where('account_id', $account->id)
            ->where('status', 'PENDING');

        if (! empty($validated['ticket'])) {
            $query->where('ticket', $validated['ticket']);
        }

        $decision = $query->oldest()->first();
        if (! $decision) {
            return response()->json(['status' => 'NO_ACTION']);
        }

        $decision->update(['status' => 'FETCHED']);

        return response()->json([
            'ticket' => $decision->ticket,
            'action' => $decision->action,
            'new_sl' => $decision->new_sl !== null ? (float) $decision->new_sl : null,
            'close_volume' => $decision->close_volume !== null ? (float) $decision->close_volume : null,
            'reason' => $decision->reason,
        ]);
    }

    public function managementApplied(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket' => ['required', 'integer'],
            'action' => ['required', 'string'],
            'status' => ['required', 'string'],
        ]);

        $decision = PositionManagementDecision::where('ticket', $validated['ticket'])
            ->where('status', 'FETCHED')
            ->first();

        if ($decision && ($response = $this->denyIfWrongAccountId($request, $decision->account_id))) {
            return $response;
        }

        PositionManagementDecision::where('ticket', $validated['ticket'])
            ->where('status', 'FETCHED')
            ->update(['status' => strtoupper($validated['status'])]);

        TradeManagementLog::where('ticket', $validated['ticket'])
            ->where('status', 'PENDING')
            ->latest()
            ->limit(1)
            ->update(['status' => 'APPLIED']);

        return response()->json(['status' => 'ok']);
    }
}
