@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')
@section('subheading', 'Pipeline health and recent activity')

@section('content')
    @if ($stats['failed_jobs'] > 0 || $stats['queued_jobs'] > 10)
        <div class="alert">
            @if ($stats['failed_jobs'] > 0)
                <p>{{ $stats['failed_jobs'] }} failed queue job(s) — run <code>php artisan queue:failed</code></p>
            @endif
            @if ($stats['queued_jobs'] > 10)
                <p style="margin-top:0.5rem">{{ $stats['queued_jobs'] }} jobs queued — ensure queue worker is running.</p>
            @endif
        </div>
    @endif

    <div class="grid-stats">
        @foreach([
            ['label' => 'Accounts', 'value' => $stats['accounts']],
            ['label' => 'Open Trades', 'value' => $stats['open_trades']],
            ['label' => 'Pending Signals', 'value' => $stats['pending_signals']],
            ['label' => 'Signals Today', 'value' => $stats['signals_today']],
            ['label' => 'Closed Today', 'value' => $stats['closed_trades_today']],
            ['label' => 'Queued Jobs', 'value' => $stats['queued_jobs']],
            ['label' => 'Failed Jobs', 'value' => $stats['failed_jobs']],
            ['label' => 'AI Calls Today', 'value' => $stats['ai_logs_today']],
        ] as $card)
            <div class="card">
                <p class="card-label">{{ $card['label'] }}</p>
                <p class="card-value">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>System Config</h2></div>
            @foreach($config as $key => $value)
                <div class="dl-row">
                    <dt>{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
            <div class="panel-footer">
                @if ($latestSnapshot)
                    Latest snapshot: <strong>{{ $latestSnapshot->symbol }}</strong>
                    for account <strong>{{ $latestSnapshot->account?->mt5_login ?? $latestSnapshot->account_id }}</strong>
                    at {{ $latestSnapshot->created_at }}
                @else
                    No market snapshots yet — MT5 has not sent data.
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Recent Signals</h2>
                <a href="{{ route('dashboard.signals') }}">View all</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Action</th>
                        <th>Conf</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentSignals as $signal)
                        <tr>
                            <td>{{ $signal->symbol }}</td>
                            <td>@include('components.status-badge', ['status' => $signal->action])</td>
                            <td>{{ $signal->confidence }}%</td>
                            <td>@include('components.status-badge', ['status' => $signal->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No signals yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="panel" style="margin-top:1.5rem">
        <div class="panel-header">
            <h2>Recent Trades</h2>
            <a href="{{ route('dashboard.trades') }}">View all</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th>Profit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentTrades as $trade)
                    <tr>
                        <td class="text-mono text-muted">{{ $trade->ticket }}</td>
                        <td>{{ $trade->symbol }}</td>
                        <td>@include('components.status-badge', ['status' => $trade->type])</td>
                        <td class="{{ ($trade->profit ?? 0) >= 0 ? 'text-profit' : 'text-loss' }}">
                            {{ $trade->profit !== null ? number_format((float) $trade->profit, 2) : '—' }}
                        </td>
                        <td>@include('components.status-badge', ['status' => $trade->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No trades yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
