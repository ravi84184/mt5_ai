<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeManagementLog extends Model
{
    protected $fillable = [
        'ticket',
        'account_id',
        'action',
        'old_sl',
        'new_sl',
        'close_volume',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'old_sl' => 'decimal:8',
            'new_sl' => 'decimal:8',
            'close_volume' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
