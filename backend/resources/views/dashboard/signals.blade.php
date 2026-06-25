@extends('layouts.dashboard')

@section('title', 'Signals')
@section('heading', 'Signals')
@section('subheading', 'AI entry decisions and execution status')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All statuses</option>
            @foreach (['PENDING', 'EXECUTED', 'REJECTED', 'CLOSED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="action" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All actions</option>
            @foreach (['BUY', 'SELL', 'WAIT'] as $a)
                <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol"
               class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Filter</button>
        <a href="{{ route('dashboard.signals') }}" class="rounded-lg px-4 py-2 text-sm text-slate-400 hover:text-white">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Symbol</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Conf</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Provider</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($signals as $signal)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-400">#{{ $signal->id }}</td>
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $signal->account?->mt5_login ?? '—' }}</td>
                            <td class="px-4 py-3 text-white">{{ $signal->symbol }}</td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $signal->action])</td>
                            <td class="px-4 py-3 text-slate-300">{{ $signal->confidence }}%</td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $signal->status])</td>
                            <td class="px-4 py-3 text-slate-400">{{ $signal->ai_provider ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs truncate text-slate-400" title="{{ $signal->rejection_reason ?? $signal->reason }}">
                                {{ $signal->rejection_reason ?? $signal->reason ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $signal->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No signals found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($signals->hasPages())
            <div class="border-t border-slate-800 px-4 py-3">{{ $signals->links() }}</div>
        @endif
    </div>
@endsection
