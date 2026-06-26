<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Account extends Model
{
    protected $fillable = [
        'mt5_login',
        'broker',
        'api_token_hash',
        'api_token_created_at',
        'ai_provider',
        'symbols',
        'trading_enabled',
        'min_confidence',
        'max_open_trades',
        'admin_notes',
        'balance',
        'equity',
        'free_margin',
        'daily_pnl',
        'pnl_date',
    ];

    protected function casts(): array
    {
        return [
            'symbols' => 'array',
            'trading_enabled' => 'boolean',
            'api_token_created_at' => 'datetime',
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'free_margin' => 'decimal:2',
            'daily_pnl' => 'decimal:2',
            'pnl_date' => 'date',
            'min_confidence' => 'integer',
            'max_open_trades' => 'integer',
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

    public static function findByApiToken(string $plainToken): ?self
    {
        if ($plainToken === '') {
            return null;
        }

        return static::where('api_token_hash', static::hashApiToken($plainToken))->first();
    }

    public static function hashApiToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function hasApiToken(): bool
    {
        return $this->api_token_hash !== null;
    }

    public function generateApiToken(): string
    {
        $plainToken = Str::random(64);

        $this->update([
            'api_token_hash' => static::hashApiToken($plainToken),
            'api_token_created_at' => now(),
        ]);

        return $plainToken;
    }

    public function revokeApiToken(): void
    {
        $this->update([
            'api_token_hash' => null,
            'api_token_created_at' => null,
        ]);
    }

    /**
     * @param  list<string>  $symbols
     */
    public static function normalizeSymbols(?string $symbolsInput): array
    {
        return collect(preg_split('/[\s,]+/', $symbolsInput ?? '') ?: [])
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function resolvedAiProvider(): string
    {
        $provider = AiProvider::tryFromMixed($this->ai_provider);

        return $provider?->value ?? config('trading.ai.provider', 'openai');
    }

    /**
     * @return list<string>
     */
    public function configuredSymbols(): array
    {
        return collect($this->symbols ?? [])
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasSymbolRestrictions(): bool
    {
        return $this->configuredSymbols() !== [];
    }

    public function isSymbolAllowed(string $symbol): bool
    {
        if (! $this->hasSymbolRestrictions()) {
            return false;
        }

        return in_array(strtoupper($symbol), $this->configuredSymbols(), true);
    }

    public function isTradingEnabled(): bool
    {
        return (bool) $this->trading_enabled;
    }

    public function resolvedMinConfidence(): int
    {
        return $this->min_confidence ?? (int) config('trading.risk.min_confidence', 80);
    }

    public function resolvedMaxOpenTrades(): int
    {
        return $this->max_open_trades ?? (int) config('trading.risk.max_open_trades', 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function toEaConfig(): array
    {
        return [
            'mt5_login' => $this->mt5_login,
            'ai_provider' => $this->resolvedAiProvider(),
            'symbols' => $this->configuredSymbols(),
            'trading_enabled' => $this->isTradingEnabled(),
            'min_confidence' => $this->resolvedMinConfidence(),
            'max_open_trades' => $this->resolvedMaxOpenTrades(),
            'has_api_token' => $this->hasApiToken(),
        ];
    }
}
