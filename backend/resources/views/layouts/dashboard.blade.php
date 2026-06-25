<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — MT5 AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="flex min-h-screen">
        <aside class="relative hidden w-56 shrink-0 border-r border-slate-800 bg-slate-900 lg:block">
            <div class="border-b border-slate-800 px-5 py-5">
                <p class="text-sm font-semibold tracking-wide text-white">MT5 AI</p>
                <p class="text-xs text-slate-400">Trading Dashboard</p>
            </div>
            <nav class="space-y-1 p-3">
                @foreach([
                    ['route' => 'dashboard.index', 'label' => 'Overview'],
                    ['route' => 'dashboard.accounts', 'label' => 'Accounts'],
                    ['route' => 'dashboard.signals', 'label' => 'Signals'],
                    ['route' => 'dashboard.trades', 'label' => 'Trades'],
                    ['route' => 'dashboard.ai-logs', 'label' => 'AI Logs'],
                ] as $item)
                    <a href="{{ route($item['route']) }}"
                       class="block rounded-lg px-3 py-2 text-sm {{ request()->routeIs($item['route'].'*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('dashboard.logout') }}" class="absolute bottom-4 left-3 right-3 lg:w-48">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                    Log out
                </button>
            </form>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-slate-800 bg-slate-900/80 px-4 py-4 backdrop-blur lg:px-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-lg font-semibold text-white">@yield('heading')</h1>
                        @hasSection('subheading')
                            <p class="text-sm text-slate-400">@yield('subheading')</p>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500">{{ now()->format('M j, Y H:i T') }}</p>
                </div>
                <nav class="mt-3 flex gap-2 overflow-x-auto lg:hidden">
                    @foreach([
                        ['route' => 'dashboard.index', 'label' => 'Overview'],
                        ['route' => 'dashboard.accounts', 'label' => 'Accounts'],
                        ['route' => 'dashboard.signals', 'label' => 'Signals'],
                        ['route' => 'dashboard.trades', 'label' => 'Trades'],
                        ['route' => 'dashboard.ai-logs', 'label' => 'AI Logs'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}"
                           class="whitespace-nowrap rounded-full px-3 py-1 text-xs {{ request()->routeIs($item['route'].'*') ? 'bg-slate-700 text-white' : 'bg-slate-800 text-slate-300' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
