@extends('layouts.dashboard')

@section('title', 'Overview')
@section('heading', 'Overview')
@section('subheading', 'Pipeline health and recent activity')

@section('content')
    @if ($stats['failed_jobs'] > 0 || $stats['queued_jobs'] > 10)
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            @if ($stats['failed_jobs'] > 0)
                <p>{{ $stats['failed_jobs'] }} failed queue job(s) — run <code class="rounded bg-slate-800 px-1">php artisan queue:failed</code></p>
            @endif
            @if ($stats['queued_jobs'] > 10)
                <p class="mt-1">{{ $stats['queued_jobs'] }} jobs queued — ensure queue worker is running.</p>
            @endif
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-slate-800 bg-slate-900">
            <div class="border-b border-slate-800 px-4 py-3">
                <h2 class="font-medium text-white">System Config</h2>
            </div>
            <dl class="divide-y divide-slate-800 px-4">
                @foreach($config as $key => $value)
                    <div class="flex justify-between gap-4 py-3 text-sm">
                        <dt class="text-slate-400">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                        <dd class="text-right text-slate-200">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($latestSnapshot)
                <div class="border-t border-slate-800 px-4 py-3 text-sm text-slate-400">
                    Latest snapshot:
                    <span class="text-slate-200">{{ $latestSnapshot->symbol }}</span>
                    for account
                    <span class="text-slate-200">{{ $latestSnapshot->account?->mt5_login ?? $latestSnapshot->account_id }}</span>
                    at {{ $latestSnapshot->created_at }}
                </div>
            @else
                <div class="border-t border-slate-800 px-4 py-3 text-sm text-slate-500">
                    No market snapshots yet — MT5 has not sent data.
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-slate-800 bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                <h2 class="font-medium text-white">Recent Signals</h2>
                <a href="{{ route('dashboard.signals') }}" class="text-xs text-sky-400 hover:text-sky-300">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Symbol</th>
                            <th class="px-4 py-2">Action</th>
                            <th class="px-4 py-2">Conf</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($recentSignals as $signal)
                            <tr>
                                <td class="px-4 py-3 text-white">{{ $signal->symbol }}</td>
                                <td class="px-4 py-3">@include('components.status-badge', ['status' => $signal->action])</td>
                                <td class="px-4 py-3 text-slate-300">{{ $signal->confidence }}%</td>
                                <td class="px-4 py-3">@include('components.status-badge', ['status' => $signal->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No signals yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-slate-800 bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
            <h2 class="font-medium text-white">Recent Trades</h2>
            <a href="{{ route('dashboard.trades') }}" class="text-xs text-sky-400 hover:text-sky-300">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Ticket</th>
                        <th class="px-4 py-2">Symbol</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Profit</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($recentTrades as $trade)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $trade->ticket }}</td>
                            <td class="px-4 py-3 text-white">{{ $trade->symbol }}</td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $trade->type])</td>
                            <td class="px-4 py-3 {{ ($trade->profit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $trade->profit !== null ? number_format((float) $trade->profit, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $trade->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No trades yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
