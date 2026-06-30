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
            'Broker' => $account->broker ?: '—',
            'API token' => $account->hasApiToken() ? 'Active (since '.$account->api_token_created_at?->format('M j, Y').')' : 'Not set',
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

    <section class="panel" style="margin-bottom:1.5rem;max-width:40rem">
        <div class="panel-header"><h2>MT5 API token (InpApiToken)</h2></div>
        <div style="padding:1rem">
            @if ($account->hasViewableApiToken())
                <p style="margin:0 0 0.75rem">Copy this value into MT5 EA <code>InpApiToken</code> for account {{ $account->mt5_login }}.</p>
                @include('admin.accounts.partials.api-token-display', ['token' => $account->plainApiToken()])
                <p style="margin:0.75rem 0 0;font-size:0.875rem;color:#94a3b8">
                    Active since {{ $account->api_token_created_at?->format('M j, Y H:i') }} UTC. Stored encrypted in the database.
                </p>
            @elseif ($account->hasApiToken())
                <p style="margin:0 0 1rem">A token is active but was created before viewable storage was enabled. Regenerate to view and copy it here.</p>
            @else
                <p style="margin:0 0 1rem">No API token yet. Generate one and paste it into MT5 EA <code>InpApiToken</code>.</p>
            @endif

            <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                @if ($account->hasApiToken())
                    <form method="POST" action="{{ route('admin.accounts.generate-token', $account) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Generate a new token? The old token will stop working immediately.')">Regenerate token</button>
                    </form>
                    <form method="POST" action="{{ route('admin.accounts.revoke-token', $account) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn" onclick="return confirm('Revoke API token? The EA will lose access until a new token is set.')">Revoke token</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.accounts.generate-token', $account) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Generate API token</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

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
