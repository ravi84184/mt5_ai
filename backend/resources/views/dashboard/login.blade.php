<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — MT5 AI Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-slate-100">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
        <div class="mb-8 text-center">
            <h1 class="text-xl font-semibold text-white">MT5 AI Dashboard</h1>
            <p class="mt-1 text-sm text-slate-400">Sign in to view trading analytics</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="mb-1 block text-sm text-slate-300">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white outline-none ring-sky-500 focus:border-sky-500 focus:ring-2"
                >
            </div>
            <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-sky-500">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
