<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class TradingSettingsService
{
    /**
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return [
            'trading.symbols' => 'symbols',
            'trading.candle_count' => 'candle_count',
            'trading.ai.provider' => 'ai.provider',
            'trading.ai.openai.api_key' => 'ai.openai.api_key',
            'trading.ai.openai.model' => 'ai.openai.model',
            'trading.ai.anthropic.api_key' => 'ai.anthropic.api_key',
            'trading.ai.anthropic.model' => 'ai.anthropic.model',
            'trading.ai.gemini.api_key' => 'ai.gemini.api_key',
            'trading.ai.gemini.model' => 'ai.gemini.model',
            'trading.risk.max_risk_per_trade_pct' => 'risk.max_risk_per_trade_pct',
            'trading.risk.min_confidence' => 'risk.min_confidence',
            'trading.risk.max_open_trades' => 'risk.max_open_trades',
            'trading.risk.max_daily_drawdown_pct' => 'risk.max_daily_drawdown_pct',
            'trading.risk.max_daily_loss' => 'risk.max_daily_loss',
            'trading.risk.max_daily_profit' => 'risk.max_daily_profit',
            'trading.risk.trading_sessions' => 'risk.trading_sessions',
        ];
    }

    public function applyToConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $trading = config('trading');

        foreach ($this->definitions() as $settingKey => $configPath) {
            $value = Setting::getValue($settingKey);
            if ($value === null || $value === '') {
                continue;
            }

            data_set($trading, $configPath, $this->castValue($configPath, $value));
        }

        config(['trading' => $trading]);
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesForForm(): array
    {
        $this->applyToConfig();

        return [
            'symbols' => implode(', ', config('trading.symbols', [])),
            'candle_count' => config('trading.candle_count'),
            'ai_provider' => config('trading.ai.provider'),
            'openai_model' => config('trading.ai.openai.model'),
            'anthropic_model' => config('trading.ai.anthropic.model'),
            'gemini_model' => config('trading.ai.gemini.model'),
            'openai_configured' => Setting::hasValue('trading.ai.openai.api_key') || (bool) config('trading.ai.openai.api_key'),
            'anthropic_configured' => Setting::hasValue('trading.ai.anthropic.api_key') || (bool) config('trading.ai.anthropic.api_key'),
            'gemini_configured' => Setting::hasValue('trading.ai.gemini.api_key') || (bool) config('trading.ai.gemini.api_key'),
            'max_risk_per_trade_pct' => config('trading.risk.max_risk_per_trade_pct'),
            'min_confidence' => config('trading.risk.min_confidence'),
            'max_open_trades' => config('trading.risk.max_open_trades'),
            'max_daily_drawdown_pct' => config('trading.risk.max_daily_drawdown_pct'),
            'max_daily_loss' => config('trading.risk.max_daily_loss'),
            'max_daily_profit' => config('trading.risk.max_daily_profit'),
            'trading_sessions' => config('trading.risk.trading_sessions'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): void
    {
        $symbols = collect(preg_split('/[\s,]+/', $input['symbols'] ?? '') ?: [])
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        Setting::setValue('trading.symbols', implode(',', $symbols));
        Setting::setValue('trading.candle_count', (string) $input['candle_count']);
        Setting::setValue('trading.ai.provider', $input['ai_provider']);
        Setting::setValue('trading.ai.openai.model', $input['openai_model']);
        Setting::setValue('trading.ai.anthropic.model', $input['anthropic_model']);
        Setting::setValue('trading.ai.gemini.model', $input['gemini_model']);

        if (! empty($input['openai_api_key'])) {
            Setting::setValue('trading.ai.openai.api_key', $input['openai_api_key']);
        }

        if (! empty($input['anthropic_api_key'])) {
            Setting::setValue('trading.ai.anthropic.api_key', $input['anthropic_api_key']);
        }

        if (! empty($input['gemini_api_key'])) {
            Setting::setValue('trading.ai.gemini.api_key', $input['gemini_api_key']);
        }

        if ($input['clear_openai_api_key'] ?? false) {
            Setting::setValue('trading.ai.openai.api_key', null);
        }

        if ($input['clear_anthropic_api_key'] ?? false) {
            Setting::setValue('trading.ai.anthropic.api_key', null);
        }

        if ($input['clear_gemini_api_key'] ?? false) {
            Setting::setValue('trading.ai.gemini.api_key', null);
        }

        Setting::setValue('trading.risk.max_risk_per_trade_pct', (string) $input['max_risk_per_trade_pct']);
        Setting::setValue('trading.risk.min_confidence', (string) $input['min_confidence']);
        Setting::setValue('trading.risk.max_open_trades', (string) $input['max_open_trades']);
        Setting::setValue('trading.risk.max_daily_drawdown_pct', (string) $input['max_daily_drawdown_pct']);
        Setting::setValue('trading.risk.trading_sessions', $input['trading_sessions']);

        Setting::setValue(
            'trading.risk.max_daily_loss',
            filled($input['max_daily_loss'] ?? null) ? (string) $input['max_daily_loss'] : null
        );

        Setting::setValue(
            'trading.risk.max_daily_profit',
            filled($input['max_daily_profit'] ?? null) ? (string) $input['max_daily_profit'] : null
        );

        $this->applyToConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'symbols' => ['nullable', 'string', 'max:500'],
            'candle_count' => ['required', 'integer', 'min:10', 'max:500'],
            'ai_provider' => ['required', 'string', 'in:'.implode(',', AiProvider::values())],
            'openai_api_key' => ['nullable', 'string', 'max:255'],
            'openai_model' => ['required', 'string', 'max:120'],
            'anthropic_api_key' => ['nullable', 'string', 'max:255'],
            'anthropic_model' => ['required', 'string', 'max:120'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['required', 'string', 'max:120'],
            'clear_openai_api_key' => ['nullable', 'boolean'],
            'clear_anthropic_api_key' => ['nullable', 'boolean'],
            'clear_gemini_api_key' => ['nullable', 'boolean'],
            'max_risk_per_trade_pct' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'min_confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'max_open_trades' => ['required', 'integer', 'min:1', 'max:50'],
            'max_daily_drawdown_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_daily_loss' => ['nullable', 'numeric', 'min:0'],
            'max_daily_profit' => ['nullable', 'numeric', 'min:0'],
            'trading_sessions' => ['required', 'string', 'max:120'],
        ];
    }

    private function castValue(string $configPath, string $value): mixed
    {
        return match ($configPath) {
            'symbols' => array_values(array_filter(array_map('trim', explode(',', $value)))),
            'candle_count', 'risk.min_confidence', 'risk.max_open_trades' => (int) $value,
            'risk.max_risk_per_trade_pct', 'risk.max_daily_drawdown_pct' => (float) $value,
            'risk.max_daily_loss', 'risk.max_daily_profit' => $value === '' ? null : (float) $value,
            default => $value,
        };
    }
}
