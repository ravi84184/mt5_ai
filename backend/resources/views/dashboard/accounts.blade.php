@extends('layouts.dashboard')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'Per-account AI provider, symbols, and trading controls')

@section('content')
    @if (session('status'))
        <div class="alert" style="border-color:rgba(16,185,129,0.3);background:rgba(16,185,129,0.1);color:#a7f3d0">{{ session('status') }}</div>
    @endif

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>MT5 Login</th>
                    <th>AI Provider</th>
                    <th>Symbols</th>
                    <th>Trading</th>
                    <th>Balance</th>
                    <th>Daily P&L</th>
                    <th>Open</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-mono">{{ $account->mt5_login }}</td>
                        <td class="text-muted">{{ $account->resolvedAiProvider() }}</td>
                        <td class="truncate" title="{{ implode(', ', $account->configuredSymbols()) }}">
                            @if ($account->hasSymbolRestrictions())
                                {{ implode(', ', $account->configuredSymbols()) }}
                            @else
                                <span class="text-dim">Not set</span>
                            @endif
                        </td>
                        <td>
                            @include('components.status-badge', ['status' => $account->trading_enabled ? 'OPEN' : 'CLOSED'])
                        </td>
                        <td>{{ number_format((float) $account->balance, 2) }}</td>
                        <td class="{{ (float) $account->daily_pnl >= 0 ? 'text-profit' : 'text-loss' }}">
                            {{ number_format((float) $account->daily_pnl, 2) }}
                        </td>
                        <td>{{ $account->open_trades_count }}</td>
                        <td><a href="{{ route('dashboard.accounts.edit', $account) }}">Manage</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            No accounts yet. Attach the EA in MT5 to register automatically.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($accounts->hasPages())
            <div class="pagination">{{ $accounts->links() }}</div>
        @endif
    </section>
@endsection
