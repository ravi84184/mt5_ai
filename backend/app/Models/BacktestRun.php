<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestRun extends Model
{
    protected $fillable = [
        'account_id',
        'symbol',
        'from_date',
        'to_date',
        'mode',
        'status',
        'params_json',
        'results_json',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'params_json' => 'array',
            'results_json' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
