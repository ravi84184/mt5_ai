<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\SignalStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PositionManagementDecision;
use App\Models\Signal;
use App\Models\Trade;
use App\Models\TradeManagementLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAdminController extends Controller
{
    public function createSignal(Account $account): View
    {
        return view('dashboard.signals-create', [
            'account' => $account,
        ]);
    }

    public function storeSignal(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'action' => ['required', 'in:BUY,SELL'],
            'entry_price' => ['nullable', 'numeric'],
            'stop_loss' => ['nullable', 'numeric'],
            'take_profit' => ['nullable', 'numeric'],
            'confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $symbol = strtoupper($validated['symbol']);

        if ($account->hasSymbolRestrictions() && ! $account->isSymbolAllowed($symbol)) {
            return back()->withErrors(['symbol' => 'Symbol is not enabled for this account.'])->withInput();
        }

        Signal::create([
            'account_id' => $account->id,
            'symbol' => $symbol,
            'action' => $validated['action'],
            'entry_price' => $validated['entry_price'] ?? null,
            'stop_loss' => $validated['stop_loss'] ?? null,
            'take_profit' => $validated['take_profit'] ?? null,
            'confidence' => $validated['confidence'],
            'reason' => $validated['reason'] ?? 'Manual signal created by admin',
            'status' => SignalStatus::Pending,
            'ai_provider' => 'admin',
        ]);

        return redirect()
            ->route('dashboard.signals')
            ->with('status', 'Manual signal created. EA will pick it up on next poll.');
    }

    public function cancelSignal(Signal $signal): RedirectResponse
    {
        if ($signal->status !== SignalStatus::Pending) {
            return back()->withErrors(['signal' => 'Only pending signals can be cancelled.']);
        }

        $signal->update([
            'status' => SignalStatus::Rejected,
            'rejection_reason' => 'Cancelled by admin',
        ]);

        return back()->with('status', "Signal #{$signal->id} cancelled.");
    }

    public function editTrade(Trade $trade): View
    {
        $trade->load(['account', 'signal']);

        return view('dashboard.trades-edit', compact('trade'));
    }

    public function updateTrade(Request $request, Trade $trade): RedirectResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'type' => ['required', 'in:BUY,SELL'],
            'lot' => ['required', 'numeric', 'min:0'],
            'entry_price' => ['nullable', 'numeric'],
            'close_price' => ['nullable', 'numeric'],
            'profit' => ['nullable', 'numeric'],
            'status' => ['required', 'in:OPEN,CLOSED'],
        ]);

        $trade->update([
            'symbol' => strtoupper($validated['symbol']),
            'type' => $validated['type'],
            'lot' => $validated['lot'],
            'entry_price' => $validated['entry_price'] ?? $trade->entry_price,
            'close_price' => $validated['close_price'] ?? $trade->close_price,
            'profit' => $validated['profit'] ?? $trade->profit,
            'status' => $validated['status'],
        ]);

        if ($validated['status'] === 'CLOSED' && $trade->signal_id) {
            $trade->signal?->update(['status' => SignalStatus::Closed]);
        }

        return redirect()
            ->route('dashboard.trades.edit', $trade)
            ->with('status', 'Trade updated.');
    }

    public function closeTrade(Request $request, Trade $trade): RedirectResponse
    {
        if ($trade->status !== 'OPEN') {
            return back()->withErrors(['trade' => 'Only open trades can be closed.']);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        PositionManagementDecision::create([
            'ticket' => $trade->ticket,
            'account_id' => $trade->account_id,
            'action' => 'CLOSE',
            'reason' => $validated['reason'] ?? 'Close requested by admin',
            'status' => 'PENDING',
        ]);

        TradeManagementLog::create([
            'ticket' => $trade->ticket,
            'account_id' => $trade->account_id,
            'action' => 'CLOSE',
            'reason' => $validated['reason'] ?? 'Close requested by admin',
            'status' => 'PENDING',
        ]);

        return back()->with('status', "Close order queued for ticket {$trade->ticket}. EA will apply on next poll.");
    }

    public function modifyTradeSl(Request $request, Trade $trade): RedirectResponse
    {
        if ($trade->status !== 'OPEN') {
            return back()->withErrors(['trade' => 'Only open trades can be modified.']);
        }

        $validated = $request->validate([
            'new_sl' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        PositionManagementDecision::create([
            'ticket' => $trade->ticket,
            'account_id' => $trade->account_id,
            'action' => 'MOVE_SL',
            'new_sl' => $validated['new_sl'],
            'reason' => $validated['reason'] ?? 'SL modified by admin',
            'status' => 'PENDING',
        ]);

        TradeManagementLog::create([
            'ticket' => $trade->ticket,
            'account_id' => $trade->account_id,
            'action' => 'MOVE_SL',
            'new_sl' => $validated['new_sl'],
            'reason' => $validated['reason'] ?? 'SL modified by admin',
            'status' => 'PENDING',
        ]);

        return back()->with('status', "SL update queued for ticket {$trade->ticket}.");
    }
}
