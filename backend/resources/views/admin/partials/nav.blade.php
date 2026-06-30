@php
    $nav = [
        ['route' => 'admin.overview', 'label' => 'Overview'],
        ['route' => 'admin.accounts.index', 'label' => 'Accounts'],
        ['route' => 'admin.signals.index', 'label' => 'Signals'],
        ['route' => 'admin.trades.index', 'label' => 'Trades'],
        ['route' => 'admin.management.index', 'label' => 'Management'],
        ['route' => 'admin.snapshots.index', 'label' => 'Market Data'],
        ['route' => 'admin.ai-logs.index', 'label' => 'AI Logs'],
        ['route' => 'admin.backtest.index', 'label' => 'Backtest'],
        ['route' => 'admin.system.index', 'label' => 'System'],
    ];
@endphp

@foreach ($nav as $item)
    <a href="{{ route($item['route']) }}"
       class="{{ request()->routeIs(str_replace('.index', '.*', $item['route']).'*') || request()->routeIs($item['route']) ? 'active' : '' }}">
        {{ $item['label'] }}
    </a>
@endforeach
