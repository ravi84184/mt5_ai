<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketSnapshot extends Model
{
    protected $fillable = [
        'account_id',
        'symbol',
        'timeframe',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
