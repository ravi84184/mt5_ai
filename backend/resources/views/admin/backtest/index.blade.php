@extends('layouts.admin')

@section('title', 'Backtest')
@section('heading', 'Backtesting')
@section('subheading', 'Replay stored market snapshots with rule-based entries')

@section('content')
    <div class="grid-2">
        <section class="panel">
            <div class="panel-header"><h2>Run backtest</h2></div>
            <form method="POST" action="{{ route('admin.backtest.store') }}" style="padding:1rem">
                @csrf
                <div class="form-group">
                    <label for="symbol">Symbol</label>
                    <input id="symbol" name="symbol" type="text" required value="{{ old('symbol', 'XAUUSD') }}">
                </div>
                <div class="form-group">
                    <label for="account_id">Account (optional)</label>
                    <select id="account_id" name="account_id">
                        <option value="">All snapshot data</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">MT5 {{ $account->mt5_login }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="from_date">From</label>
                    <input id="from_date" name="from_date" type="date" required value="{{ old('from_date', now()->subDays(30)->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label for="to_date">To</label>
                    <input id="to_date" name="to_date" type="date" required value="{{ old('to_date', now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label for="strategy">Strategy</label>
                    <select id="strategy" name="strategy">
                        <option value="balanced">Balanced</option>
                        <option value="conservative">Conservative</option>
                        <option value="active">Active</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Run backtest</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Recent runs</h2></div>
            <table>
                <thead><tr><th>ID</th><th>Symbol</th><th>Status</th><th>Win%</th><th>Total R</th><th></th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    @php $r = $run->results_json ?? []; @endphp
                    <tr>
                        <td>#{{ $run->id }}</td>
                        <td>{{ $run->symbol }}</td>
                        <td>@include('components.status-badge', ['status' => $run->status])</td>
                        <td>{{ $r['win_rate'] ?? '—' }}@if(isset($r['win_rate']))%@endif</td>
                        <td>{{ $r['total_r'] ?? '—' }}</td>
                        <td><a href="{{ route('admin.backtest.show', $run) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No backtests yet — need MT5 market snapshots</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $runs->links() }}
        </section>
    </div>
@endsection
