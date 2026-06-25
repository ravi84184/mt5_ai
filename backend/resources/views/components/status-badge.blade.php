@php
    $colors = match (strtoupper((string) $status)) {
        'PENDING' => 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
        'EXECUTED', 'APPLIED', 'SUCCESS', 'OPEN' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
        'REJECTED', 'ERROR', 'FAILED' => 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
        'CLOSED', 'WAIT', 'HOLD', 'NO_ACTION' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
        'BUY' => 'bg-sky-500/15 text-sky-300 ring-sky-500/30',
        'SELL' => 'bg-violet-500/15 text-violet-300 ring-violet-500/30',
        default => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
    };
    $label = $status instanceof \BackedEnum ? $status->value : (string) $status;
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $colors }}">
    {{ $label }}
</span>
