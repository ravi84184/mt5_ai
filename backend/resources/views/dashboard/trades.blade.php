@extends('layouts.dashboard')

@section('title', 'Trades')
@section('heading', 'Trades')
@section('subheading', 'Executed positions and P&L')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="">All statuses</option>
            @foreach (['OPEN', 'CLOSED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Filter</button>
        <a href="{{ route('dashboard.trades') }}" class="rounded-lg px-4 py-2 text-sm text-slate-400 hover:text-white">Clear</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Ticket</th>
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Symbol</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Lot</th>
                        <th class="px-4 py-3">Entry</th>
                        <th class="px-4 py-3">Close</th>
                        <th class="px-4 py-3">Profit</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Signal</th>
                        <th class="px-4 py-3">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($trades as $trade)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-300">{{ $trade->ticket }}</td>
                            <td class="px-4 py-3 font-mono text-slate-400">{{ $trade->account?->mt5_login ?? '—' }}</td>
                            <td class="px-4 py-3 text-white">{{ $trade->symbol }}</td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $trade->type])</td>
                            <td class="px-4 py-3 text-slate-300">{{ $trade->lot }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $trade->entry_price }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $trade->close_price ?? '—' }}</td>
                            <td class="px-4 py-3 {{ ($trade->profit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $trade->profit !== null ? number_format((float) $trade->profit, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3">@include('components.status-badge', ['status' => $trade->status])</td>
                            <td class="px-4 py-3 text-slate-400">
                                @if ($trade->signal_id)
                                    <a href="{{ route('dashboard.signals', ['status' => '']) }}#{{ $trade->signal_id }}" class="text-sky-400 hover:text-sky-300">#{{ $trade->signal_id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $trade->updated_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-10 text-center text-slate-500">No trades found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($trades->hasPages())
            <div class="border-t border-slate-800 px-4 py-3">{{ $trades->links() }}</div>
        @endif
    </div>
@endsection
