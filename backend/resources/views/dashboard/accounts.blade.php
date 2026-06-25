@extends('layouts.dashboard')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'Connected MT5 trading accounts')

@section('content')
    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>MT5 Login</th>
                    <th>Balance</th>
                    <th>Equity</th>
                    <th>Free Margin</th>
                    <th>Daily P&L</th>
                    <th>Signals</th>
                    <th>Open</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-mono">{{ $account->mt5_login }}</td>
                        <td>{{ number_format((float) $account->balance, 2) }}</td>
                        <td>{{ number_format((float) $account->equity, 2) }}</td>
                        <td>{{ number_format((float) $account->free_margin, 2) }}</td>
                        <td class="{{ (float) $account->daily_pnl >= 0 ? 'text-profit' : 'text-loss' }}">
                            {{ number_format((float) $account->daily_pnl, 2) }}
                        </td>
                        <td>{{ $account->signals_count }}</td>
                        <td>{{ $account->open_trades_count }}</td>
                        <td class="text-dim">{{ $account->updated_at }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">
                            No accounts yet. Attach the EA in MT5 to register automatically.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($accounts->hasPages())
            <div class="pagination">{{ $accounts->links() }}</div>
        @endif
    </section>
@endsection
