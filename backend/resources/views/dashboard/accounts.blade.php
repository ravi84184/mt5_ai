@extends('layouts.dashboard')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'Connected MT5 trading accounts')

@section('content')
    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">MT5 Login</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Equity</th>
                        <th class="px-4 py-3">Free Margin</th>
                        <th class="px-4 py-3">Daily P&L</th>
                        <th class="px-4 py-3">Signals</th>
                        <th class="px-4 py-3">Open</th>
                        <th class="px-4 py-3">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="px-4 py-3 font-mono text-white">{{ $account->mt5_login }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ number_format((float) $account->balance, 2) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ number_format((float) $account->equity, 2) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ number_format((float) $account->free_margin, 2) }}</td>
                            <td class="px-4 py-3 {{ (float) $account->daily_pnl >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ number_format((float) $account->daily_pnl, 2) }}
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $account->signals_count }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $account->open_trades_count }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $account->updated_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                No accounts yet. Attach the EA in MT5 to register automatically.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($accounts->hasPages())
            <div class="border-t border-slate-800 px-4 py-3">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
@endsection
