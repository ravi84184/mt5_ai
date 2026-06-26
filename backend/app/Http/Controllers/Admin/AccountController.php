<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\Signal;
use App\Models\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $accounts = Account::query()
            ->withCount([
                'signals',
                'trades',
                'trades as open_trades_count' => fn ($q) => $q->where('status', 'OPEN'),
            ])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('admin.accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('admin.accounts.create', [
            'providers' => AiProvider::cases(),
            'defaultProvider' => config('trading.ai.provider'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mt5_login' => ['required', 'integer', 'unique:accounts,mt5_login'],
            'broker' => ['nullable', 'string', 'max:120'],
            'ai_provider' => ['nullable', 'string', 'in:'.implode(',', AiProvider::values())],
            'symbols' => ['nullable', 'string', 'max:500'],
            'trading_enabled' => ['nullable', 'boolean'],
            'min_confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_open_trades' => ['nullable', 'integer', 'min:1', 'max:50'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'generate_api_token' => ['nullable', 'boolean'],
        ]);

        $symbols = Account::normalizeSymbols($validated['symbols'] ?? '');

        $account = Account::create([
            'mt5_login' => $validated['mt5_login'],
            'broker' => $validated['broker'] ?? null,
            'ai_provider' => $validated['ai_provider'] ?: null,
            'symbols' => $symbols === [] ? null : $symbols,
            'trading_enabled' => $request->boolean('trading_enabled'),
            'min_confidence' => $validated['min_confidence'] ?? null,
            'max_open_trades' => $validated['max_open_trades'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        $redirect = redirect()->route('admin.accounts.show', $account)
            ->with('status', "Account {$account->mt5_login} created.");

        if ($request->boolean('generate_api_token')) {
            $plainToken = $account->generateApiToken();

            return $redirect
                ->with('api_token', $plainToken)
                ->with('status', "Account {$account->mt5_login} created. Copy the API token below into MT5 InpApiToken — it is shown only once.");
        }

        return $redirect;
    }

    public function show(Account $account): View
    {
        $account->loadCount(['signals', 'trades']);

        return view('admin.accounts.show', [
            'account' => $account,
            'recentSignals' => Signal::where('account_id', $account->id)->latest()->limit(10)->get(),
            'openTrades' => Trade::where('account_id', $account->id)->where('status', 'OPEN')->latest()->get(),
            'recentLogs' => AiInteractionLog::where('account_id', $account->id)->latest()->limit(5)->get(),
        ]);
    }

    public function edit(Account $account): View
    {
        return view('admin.accounts.edit', [
            'account' => $account,
            'providers' => AiProvider::cases(),
            'defaultProvider' => config('trading.ai.provider'),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'broker' => ['nullable', 'string', 'max:120'],
            'ai_provider' => ['nullable', 'string', 'in:'.implode(',', AiProvider::values())],
            'symbols' => ['nullable', 'string', 'max:500'],
            'trading_enabled' => ['nullable', 'boolean'],
            'min_confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_open_trades' => ['nullable', 'integer', 'min:1', 'max:50'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $symbols = Account::normalizeSymbols($validated['symbols'] ?? '');

        $account->update([
            'broker' => $validated['broker'] ?? null,
            'ai_provider' => $validated['ai_provider'] ?: null,
            'symbols' => $symbols === [] ? null : $symbols,
            'trading_enabled' => $request->boolean('trading_enabled'),
            'min_confidence' => $validated['min_confidence'] ?? null,
            'max_open_trades' => $validated['max_open_trades'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.accounts.show', $account)
            ->with('status', 'Account settings saved.');
    }

    public function generateToken(Account $account): RedirectResponse
    {
        $plainToken = $account->generateApiToken();

        return redirect()
            ->route('admin.accounts.show', $account)
            ->with('api_token', $plainToken)
            ->with('status', 'New API token generated. Copy it into MT5 InpApiToken now — it will not be shown again.');
    }

    public function revokeToken(Account $account): RedirectResponse
    {
        $account->revokeApiToken();

        return back()->with('status', 'API token revoked. Generate a new one before the EA can connect with a per-account token.');
    }

    public function toggleTrading(Account $account): RedirectResponse
    {
        $account->update(['trading_enabled' => ! $account->trading_enabled]);

        $state = $account->trading_enabled ? 'enabled' : 'disabled';

        return back()->with('status', "Trading {$state} for account {$account->mt5_login}.");
    }
}
