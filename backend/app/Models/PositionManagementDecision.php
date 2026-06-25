<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionManagementDecision extends Model
{
    protected $fillable = [
        'ticket',
        'account_id',
        'action',
        'new_sl',
        'close_volume',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'new_sl' => 'decimal:8',
            'close_volume' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
