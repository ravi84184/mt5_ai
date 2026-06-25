@php
    $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $class = match (strtoupper($statusValue)) {
        'PENDING' => 'badge-pending',
        'EXECUTED', 'APPLIED', 'SUCCESS', 'OPEN' => 'badge-success',
        'REJECTED', 'ERROR', 'FAILED' => 'badge-error',
        'BUY' => 'badge-buy',
        'SELL' => 'badge-sell',
        default => 'badge-muted',
    };
@endphp
<span class="badge {{ $class }}">{{ $statusValue }}</span>
