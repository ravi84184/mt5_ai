@extends('layouts.admin')

@section('title', 'Market Data')
@section('heading', 'Market snapshots')
@section('subheading', 'Raw market data received from MT5')

@section('content')
    <form method="GET" class="filters">
        <input type="text" name="symbol" value="{{ request('symbol') }}" placeholder="Symbol">
        <button type="submit" class="btn">Filter</button>
    </form>

    <section class="panel">
        <table>
            <thead><tr><th>ID</th><th>Account</th><th>Symbol</th><th>TF</th><th>Received</th></tr></thead>
            <tbody>
                @forelse ($snapshots as $s)
                    <tr>
                        <td>#{{ $s->id }}</td>
                        <td>{{ $s->account?->mt5_login }}</td>
                        <td>{{ $s->symbol }}</td>
                        <td>{{ $s->timeframe }}</td>
                        <td class="text-dim">{{ $s->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No snapshots</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($snapshots->hasPages())<div class="pagination">{{ $snapshots->links() }}</div>@endif
    </section>
@endsection
