<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAccountController extends Controller
{
    public function edit(Account $account): View
    {
        return view('dashboard.accounts-edit', [
            'account' => $account,
            'providers' => AiProvider::cases(),
            'defaultProvider' => config('trading.ai.provider'),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['nullable', 'string', 'in:'.implode(',', AiProvider::values())],
            'symbols' => ['nullable', 'string', 'max:500'],
            'trading_enabled' => ['nullable', 'boolean'],
            'min_confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_open_trades' => ['nullable', 'integer', 'min:1', 'max:50'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $symbols = collect(preg_split('/[\s,]+/', $validated['symbols'] ?? '') ?: [])
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $account->update([
            'ai_provider' => $validated['ai_provider'] ?: null,
            'symbols' => $symbols === [] ? null : $symbols,
            'trading_enabled' => $request->boolean('trading_enabled'),
            'min_confidence' => $validated['min_confidence'] ?? null,
            'max_open_trades' => $validated['max_open_trades'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return redirect()
            ->route('dashboard.accounts.edit', $account)
            ->with('status', 'Account settings saved.');
    }
}
