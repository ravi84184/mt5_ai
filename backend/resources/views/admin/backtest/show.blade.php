@extends('layouts.admin')

@section('title', 'Backtest #'.$run->id)
@section('heading', 'Backtest #'.$run->id)
@section('subheading', $run->symbol.' · '.$run->from_date?->format('Y-m-d').' → '.$run->to_date?->format('Y-m-d'))

@section('content')
    <p><a href="{{ route('admin.backtest.index') }}">← Back to backtests</a></p>

    @if(in_array($run->status, ['PENDING', 'RUNNING'], true))
        <div class="alert">Backtest running… refresh in a few seconds.</div>
        <meta http-equiv="refresh" content="5">
    @endif

    @if($run->status === 'FAILED')
        <div class="alert">{{ $run->error_message }}</div>
    @endif

    @php $r = $run->results_json ?? []; @endphp
    @if($run->status === 'COMPLETED')
        <div class="grid-stats" style="margin-top:1rem">
            @foreach([
                ['Trades', $r['total_trades'] ?? 0],
                ['Win rate', ($r['win_rate'] ?? 0).'%'],
                ['Total R', $r['total_r'] ?? 0],
                ['Max DD (R)', $r['max_drawdown_r'] ?? 0],
            ] as [$label, $value])
                <div class="card"><p class="card-label">{{ $label }}</p><p class="card-value">{{ $value }}</p></div>
            @endforeach
        </div>

        <section class="panel" style="margin-top:1.5rem">
            <div class="panel-header"><h2>Simulated trades</h2></div>
            <table>
                <thead><tr><th>Opened</th><th>Action</th><th>Outcome</th><th>R</th></tr></thead>
                <tbody>
                @foreach($r['trades'] ?? [] as $t)
                    <tr>
                        <td>{{ $t['opened_at'] ?? '—' }}</td>
                        <td>{{ $t['action'] ?? '—' }}</td>
                        <td>{{ $t['outcome'] ?? '—' }}</td>
                        <td>{{ $t['r_multiple'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
