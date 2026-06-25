<?php

namespace App\Models;

use App\Enums\SignalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Signal extends Model
{
    protected $fillable = [
        'account_id',
        'symbol',
        'action',
        'entry_price',
        'stop_loss',
        'take_profit',
        'confidence',
        'reason',
        'status',
        'rejection_reason',
        'ticket',
        'ai_provider',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'decimal:8',
            'stop_loss' => 'decimal:8',
            'take_profit' => 'decimal:8',
            'confidence' => 'integer',
            'status' => SignalStatus::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function trade(): HasOne
    {
        return $this->hasOne(Trade::class);
    }

    public function scopePendingForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId)
            ->where('status', SignalStatus::Pending)
            ->whereIn('action', ['BUY', 'SELL'])
            ->orderByDesc('confidence')
            ->orderBy('created_at');
    }
}
