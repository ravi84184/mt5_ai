@extends('layouts.admin')

@section('title', 'Account '.$account->mt5_login)
@section('heading', 'Account '.$account->mt5_login)
@section('subheading', 'Profile, config, and recent activity')

@section('content')
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
        <a href="{{ route('admin.accounts.index') }}">← Accounts</a>
        <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-primary">Edit settings</a>
        <a href="{{ route('admin.signals.create', $account) }}" class="btn">Create signal</a>
        <form method="POST" action="{{ route('admin.accounts.toggle-trading', $account) }}" style="display:inline">
            @csrf
            <button type="submit" class="btn">{{ $account->trading_enabled ? 'Disable trading' : 'Enable trading' }}</button>
        </form>
    </div>

    <div class="grid-stats" style="margin-bottom:1.5rem">
        @foreach([
            'AI Provider' => $account->resolvedAiProvider(),
            'Symbols' => $account->hasSymbolRestrictions() ? implode(', ', $account->configuredSymbols()) : 'Not set',
            'Trading' => $account->trading_enabled ? 'Enabled' : 'Disabled',
            'Min confidence' => $account->resolvedMinConfidence().'%',
            'Max open trades' => $account->resolvedMaxOpenTrades(),
            'Balance' => number_format((float) $account->balance, 2),
            'Equity' => number_format((float) $account->equity, 2),
            'Daily P&L' => number_format((float) $account->daily_pnl, 2),
        ] as $label => $value)
            <div class="card">
                <p class="card-label">{{ $label }}</p>
                <p style="margin:0.25rem 0 0;font-size:0.875rem">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($account->admin_notes)
        <section class="panel" style="margin-bottom:1.5rem">
            <div class="panel-header"><h2>Admin notes</h2></div>
            <div class="panel-footer">{{ $account->admin_notes }}</div>
        </section>
    @endif

    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>Recent signals</h2></div>
            <table>
                <thead><tr><th>ID</th><th>Symbol</th><th>Action</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentSignals as $signal)
                        <tr>
                            <td><a href="{{ route('admin.signals.show', $signal) }}">#{{ $signal->id }}</a></td>
                            <td>{{ $signal->symbol }}</td>
                            <td>@include('components.status-badge', ['status' => $signal->action])</td>
                            <td>@include('components.status-badge', ['status' => $signal->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No signals</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Open trades</h2></div>
            <table>
                <thead><tr><th>Ticket</th><th>Symbol</th><th>Profit</th></tr></thead>
                <tbody>
                    @forelse ($openTrades as $trade)
                        <tr>
                            <td><a href="{{ route('admin.trades.edit', $trade) }}">{{ $trade->ticket }}</a></td>
                            <td>{{ $trade->symbol }}</td>
                            <td>{{ $trade->profit ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No open trades</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
