@extends('layouts.admin')

@section('title', 'Overview')
@section('heading', 'Super Admin Overview')
@section('subheading', 'Platform health, trading activity, and alerts')

@section('content')
    @if ($stats['failed_jobs'] > 0)
        <div class="alert">
            {{ $stats['failed_jobs'] }} failed queue job(s).
            <a href="{{ route('admin.system.queue') }}">View queue →</a>
        </div>
    @endif

    @if ($accountsNeedingSetup > 0)
        <div class="alert" style="border-color:rgba(245,158,11,0.3);background:rgba(245,158,11,0.1);color:#fde68a">
            {{ $accountsNeedingSetup }} account(s) have no symbols configured.
            <a href="{{ route('admin.accounts.index') }}" style="color:#fde68a">Configure accounts →</a>
        </div>
    @endif

    <div class="grid-stats">
        @foreach([
            ['Accounts', $stats['accounts']],
            ['Active trading', $stats['active_accounts']],
            ['Open trades', $stats['open_trades']],
            ['Pending signals', $stats['pending_signals']],
            ['Pending mgmt', $stats['pending_management']],
            ['Queued jobs', $stats['queued_jobs']],
            ['Failed jobs', $stats['failed_jobs']],
            ['AI calls today', $stats['ai_logs_today']],
        ] as [$label, $value])
            <div class="card">
                <p class="card-label">{{ $label }}</p>
                <p class="card-value">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid-2" style="margin-top:1.5rem">
        <section class="panel">
            <div class="panel-header">
                <h2>Quick actions</h2>
            </div>
            <div style="padding:1rem;display:flex;flex-wrap:gap:0.5rem">
                <a href="{{ route('admin.accounts.index') }}" class="btn">Manage accounts</a>
                <a href="{{ route('admin.signals.index') }}" class="btn">View signals</a>
                <a href="{{ route('admin.management.index') }}" class="btn">Management queue</a>
                <a href="{{ route('admin.system.index') }}" class="btn">System config</a>
                <a href="{{ route('admin.system.queue') }}" class="btn">Queue monitor</a>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Latest snapshot</h2></div>
            <div class="panel-footer">
                @if ($latestSnapshot)
                    {{ $latestSnapshot->symbol }} · account {{ $latestSnapshot->account?->mt5_login }} · {{ $latestSnapshot->created_at }}
                @else
                    No market data received yet.
                @endif
            </div>
        </section>
    </div>

    <div class="grid-2" style="margin-top:1.5rem">
        <section class="panel">
            <div class="panel-header">
                <h2>Recent signals</h2>
                <a href="{{ route('admin.signals.index') }}">View all</a>
            </div>
            <table>
                <thead><tr><th>Symbol</th><th>Action</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentSignals as $signal)
                        <tr>
                            <td><a href="{{ route('admin.signals.show', $signal) }}">{{ $signal->symbol }}</a></td>
                            <td>@include('components.status-badge', ['status' => $signal->action])</td>
                            <td>@include('components.status-badge', ['status' => $signal->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No signals</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Recent trades</h2>
                <a href="{{ route('admin.trades.index') }}">View all</a>
            </div>
            <table>
                <thead><tr><th>Ticket</th><th>Symbol</th><th>Profit</th></tr></thead>
                <tbody>
                    @forelse ($recentTrades as $trade)
                        <tr>
                            <td><a href="{{ route('admin.trades.edit', $trade) }}">{{ $trade->ticket }}</a></td>
                            <td>{{ $trade->symbol }}</td>
                            <td class="{{ ($trade->profit ?? 0) >= 0 ? 'text-profit' : 'text-loss' }}">{{ $trade->profit ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No trades</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
