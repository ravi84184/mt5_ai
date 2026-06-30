<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunBacktestJob;
use App\Models\Account;
use App\Models\BacktestRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BacktestController extends Controller
{
    public function index(): View
    {
        return view('admin.backtest.index', [
            'runs' => BacktestRun::with('account')->latest()->paginate(20),
            'accounts' => Account::orderBy('mt5_login')->get(),
            'symbols' => Account::query()->whereNotNull('symbols')->get()
                ->flatMap(fn ($a) => $a->configuredSymbols())
                ->unique()->sort()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:32'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'strategy' => ['nullable', 'string', 'in:conservative,balanced,active'],
        ]);

        $run = BacktestRun::create([
            'account_id' => $validated['account_id'] ?? null,
            'symbol' => strtoupper($validated['symbol']),
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'mode' => 'rules',
            'status' => 'PENDING',
            'params_json' => [
                'strategy' => $validated['strategy'] ?? config('trading.ai_entry.strategy'),
            ],
        ]);

        RunBacktestJob::dispatch($run->id);

        return redirect()->route('admin.backtest.show', $run)
            ->with('status', 'Backtest queued. Refresh in a few seconds.');
    }

    public function show(BacktestRun $backtest): View
    {
        $backtest->load('account');

        return view('admin.backtest.show', ['run' => $backtest]);
    }
}
