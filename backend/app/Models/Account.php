<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'mt5_login',
        'broker',
        'balance',
        'equity',
        'free_margin',
        'daily_pnl',
        'pnl_date',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'free_margin' => 'decimal:2',
            'daily_pnl' => 'decimal:2',
            'pnl_date' => 'date',
        ];
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public static function findOrCreateFromMt5(array $accountData): self
    {
        return static::updateOrCreate(
            ['mt5_login' => $accountData['login']],
            [
                'balance' => $accountData['balance'] ?? 0,
                'equity' => $accountData['equity'] ?? 0,
                'free_margin' => $accountData['free_margin'] ?? 0,
            ]
        );
    }
}
