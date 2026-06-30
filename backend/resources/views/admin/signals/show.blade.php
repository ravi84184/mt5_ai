@extends('layouts.admin')

@section('title', 'Signal #'.$signal->id)
@section('heading', 'Signal #'.$signal->id)
@section('subheading', $signal->symbol.' · '.$signal->action.' · '.($signal->status->value ?? $signal->status))

@section('content')
    @php $status = $signal->status->value ?? $signal->status; @endphp
    @if ($status === 'PENDING')
        <div class="alert" style="border-color:rgba(245,158,11,0.4);background:rgba(245,158,11,0.12);color:#fde68a;margin-bottom:1rem">
            <strong>Waiting for MT5 EA</strong> — status is PENDING. The EA polls every ~7 seconds.
            If this stays pending, check MT5 Experts tab for errors (AutoTrading, WebRequest, SL/TP, symbol name).
        </div>
    @elseif ($status === 'REJECTED')
        <div class="alert" style="border-color:rgba(248,113,113,0.4);background:rgba(248,113,113,0.12);color:#fecaca;margin-bottom:1rem">
            <strong>Not sent to MT5</strong> — this signal was rejected before or during execution.
        </div>
    @endif
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem">
        <a href="{{ route('admin.signals.index') }}">← Signals</a>
        @if (($signal->status->value ?? $signal->status) === 'PENDING')
            <form method="POST" action="{{ route('admin.signals.cancel', $signal) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn">Cancel signal</button>
            </form>
        @endif
    </div>

    <div class="grid-stats" style="margin-bottom:1.5rem">
        @foreach([
            'Account' => $signal->account?->mt5_login,
            'Symbol' => $signal->symbol,
            'Action' => $signal->action,
            'Confidence' => $signal->confidence.'%',
            'Provider' => $signal->ai_provider ?? '—',
            'Entry' => $signal->entry_price ?? '—',
            'SL' => $signal->stop_loss ?? '—',
            'TP' => $signal->take_profit ?? '—',
        ] as $label => $value)
            <div class="card"><p class="card-label">{{ $label }}</p><p style="margin:0.25rem 0 0;font-size:0.875rem">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="panel">
        <div class="panel-header"><h2>Reason</h2></div>
        <div class="panel-footer">{{ $signal->reason ?? '—' }}</div>
        @if ($signal->rejection_reason)
            <div class="panel-footer" style="color:#fda4af">Rejected: {{ $signal->rejection_reason }}</div>
        @endif
    </section>

    @if ($signal->trade)
        <section class="panel" style="margin-top:1rem">
            <div class="panel-header"><h2>Linked trade</h2></div>
            <div class="panel-footer">
                Ticket <a href="{{ route('admin.trades.edit', $signal->trade) }}">{{ $signal->trade->ticket }}</a>
                · {{ $signal->trade->status }} · profit {{ $signal->trade->profit ?? '—' }}
            </div>
        </section>
    @endif
@endsection
