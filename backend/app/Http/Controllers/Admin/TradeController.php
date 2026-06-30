<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SignalStatus;
use App\Http\Controllers\Controller;
use App\Models\PositionManagementDecision;
use App\Models\Trade;
use App\Models\TradeManagementLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TradeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Trade::with(['account', 'signal'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        if ($accountId = $request->query('account_id')) {
            $query->where('account_id', $accountId);
        }

        $trades = $query->paginate(25)->withQueryString();

        return view('admin.trades.index', compact('trades'));
    }

    public function edit(Trade $trade): View
    {
        $trade->load(['account', 'signal']);

        return view('admin.trades.edit', compact('trade'));
    }

    public function update(Request $request, Trade $trade): RedirectResponse
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
            ->route('admin.trades.edit', $trade)
            ->with('status', 'Trade updated.');
    }

    public function close(Request $request, Trade $trade): RedirectResponse
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

    public function modifySl(Request $request, Trade $trade): RedirectResponse
    {
        if ($trade->status !== 'OPEN') {
            return back()->withErrors(['trade' => 'Only open trades can be modified.']);
        }

        $validated = $request->validate([
            'new_sl' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        PositionManagementDecision::where('account_id', $trade->account_id)
            ->where('ticket', $trade->ticket)
            ->where('status', 'PENDING')
            ->update(['status' => 'CANCELLED']);

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
