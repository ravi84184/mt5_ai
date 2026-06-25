<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — MT5 AI</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <strong>MT5 AI</strong>
                <span>Trading Dashboard</span>
            </div>
            <nav class="sidebar-nav">
                @foreach([
                    ['route' => 'dashboard.index', 'label' => 'Overview'],
                    ['route' => 'dashboard.accounts', 'label' => 'Accounts'],
                    ['route' => 'dashboard.signals', 'label' => 'Signals'],
                    ['route' => 'dashboard.trades', 'label' => 'Trades'],
                    ['route' => 'dashboard.ai-logs', 'label' => 'AI Logs'],
                ] as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route'].'*') ? 'active' : '' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('dashboard.logout') }}" class="sidebar-logout">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%">Log out</button>
            </form>
        </aside>

        <div class="main">
            <header class="header">
                <div class="header-top">
                    <div>
                        <h1>@yield('heading')</h1>
                        @hasSection('subheading')
                            <p>@yield('subheading')</p>
                        @endif
                    </div>
                    <p class="header-time">{{ now()->format('M j, Y H:i T') }}</p>
                </div>
                <nav class="mobile-nav">
                    @foreach([
                        ['route' => 'dashboard.index', 'label' => 'Overview'],
                        ['route' => 'dashboard.accounts', 'label' => 'Accounts'],
                        ['route' => 'dashboard.signals', 'label' => 'Signals'],
                        ['route' => 'dashboard.trades', 'label' => 'Trades'],
                        ['route' => 'dashboard.ai-logs', 'label' => 'AI Logs'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route'].'*') ? 'active' : '' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </header>

            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
