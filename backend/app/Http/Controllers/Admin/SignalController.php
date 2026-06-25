<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SignalStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Signal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignalController extends Controller
{
    public function index(Request $request): View
    {
        $query = Signal::with('account')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        if ($action = $request->query('action')) {
            $query->where('action', strtoupper($action));
        }

        if ($symbol = $request->query('symbol')) {
            $query->where('symbol', strtoupper($symbol));
        }

        if ($accountId = $request->query('account_id')) {
            $query->where('account_id', $accountId);
        }

        $signals = $query->paginate(25)->withQueryString();

        return view('admin.signals.index', compact('signals'));
    }

    public function show(Signal $signal): View
    {
        $signal->load(['account', 'trade']);

        return view('admin.signals.show', compact('signal'));
    }

    public function create(Account $account): View
    {
        return view('admin.signals.create', compact('account'));
    }

    public function store(Request $request, Account $account): RedirectResponse
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

        $signal = Signal::create([
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
            ->route('admin.signals.show', $signal)
            ->with('status', 'Manual signal created. EA will pick it up on next poll.');
    }

    public function cancel(Signal $signal): RedirectResponse
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
}
