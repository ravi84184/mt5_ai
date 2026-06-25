<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteractionLog extends Model
{
    protected $fillable = [
        'account_id',
        'signal_id',
        'analysis_type',
        'provider',
        'model',
        'symbol',
        'ticket',
        'input_json',
        'system_prompt',
        'user_prompt',
        'output_json',
        'status',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'output_json' => 'array',
            'ticket' => 'integer',
            'duration_ms' => 'integer',
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
