@extends('layouts.admin')

@section('title', 'Trades')
@section('heading', 'Trades')
@section('subheading', 'Manage open and closed positions')

@section('content')
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All</option>
            <option value="OPEN" @selected(request('status') === 'OPEN')>OPEN</option>
            <option value="CLOSED" @selected(request('status') === 'CLOSED')>CLOSED</option>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>

    <section class="panel">
        <table>
            <thead>
                <tr><th>Ticket</th><th>Account</th><th>Symbol</th><th>Type</th><th>Lot</th><th>Profit</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($trades as $trade)
                    <tr>
                        <td class="text-mono">{{ $trade->ticket }}</td>
                        <td>{{ $trade->account?->mt5_login }}</td>
                        <td>{{ $trade->symbol }}</td>
                        <td>@include('components.status-badge', ['status' => $trade->type])</td>
                        <td>{{ $trade->lot }}</td>
                        <td class="{{ ($trade->profit ?? 0) >= 0 ? 'text-profit' : 'text-loss' }}">{{ $trade->profit ?? '—' }}</td>
                        <td>@include('components.status-badge', ['status' => $trade->status])</td>
                        <td><a href="{{ route('admin.trades.edit', $trade) }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No trades</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($trades->hasPages())<div class="pagination">{{ $trades->links() }}</div>@endif
    </section>
@endsection
