@extends('layouts.dashboard')

@section('title', 'Trades')
@section('heading', 'Trades')
@section('subheading', 'Executed positions and P&L')

@section('content')
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['OPEN', 'CLOSED'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('dashboard.trades') }}" class="btn btn-ghost">Clear</a>
    </form>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Account</th>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th>Lot</th>
                    <th>Entry</th>
                    <th>Close</th>
                    <th>Profit</th>
                    <th>Status</th>
                    <th>Signal</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trades as $trade)
                    <tr>
                        <td class="text-mono text-muted">{{ $trade->ticket }}</td>
                        <td class="text-mono text-dim">{{ $trade->account?->mt5_login ?? '—' }}</td>
                        <td>{{ $trade->symbol }}</td>
                        <td>@include('components.status-badge', ['status' => $trade->type])</td>
                        <td>{{ $trade->lot }}</td>
                        <td>{{ $trade->entry_price }}</td>
                        <td>{{ $trade->close_price ?? '—' }}</td>
                        <td class="{{ ($trade->profit ?? 0) >= 0 ? 'text-profit' : 'text-loss' }}">
                            {{ $trade->profit !== null ? number_format((float) $trade->profit, 2) : '—' }}
                        </td>
                        <td>@include('components.status-badge', ['status' => $trade->status])</td>
                        <td>
                            @if ($trade->signal_id)
                                #{{ $trade->signal_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-dim">{{ $trade->updated_at }}</td>
                        <td><a href="{{ route('dashboard.trades.edit', $trade) }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="empty">No trades found</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($trades->hasPages())
            <div class="pagination">{{ $trades->links() }}</div>
        @endif
    </section>
@endsection
