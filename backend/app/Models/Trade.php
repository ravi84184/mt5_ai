<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'ticket',
        'signal_id',
        'account_id',
        'symbol',
        'type',
        'lot',
        'entry_price',
        'close_price',
        'profit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lot' => 'decimal:4',
            'entry_price' => 'decimal:8',
            'close_price' => 'decimal:8',
            'profit' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}
