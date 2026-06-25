<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') — MT5 AI</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <strong>MT5 AI</strong>
                <span>Super Admin</span>
            </div>
            <nav class="sidebar-nav">
                @include('admin.partials.nav')
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-logout">
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
                    @include('admin.partials.nav')
                </nav>
            </header>

            <main class="content">
                @hasSection('hide_flash')
                @else
                    @include('admin.partials.flash')
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
