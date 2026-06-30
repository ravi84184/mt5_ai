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
            'trading.ai.consensus.enabled' => 'ai.consensus.enabled',
            'trading.ai.consensus.providers' => 'ai.consensus.providers',
            'trading.ai.consensus.min_agree' => 'ai.consensus.min_agree',
            'trading.risk.max_risk_per_trade_pct' => 'risk.max_risk_per_trade_pct',
            'trading.risk.min_confidence' => 'risk.min_confidence',
            'trading.risk.max_open_trades' => 'risk.max_open_trades',
            'trading.risk.max_daily_drawdown_pct' => 'risk.max_daily_drawdown_pct',
            'trading.risk.max_daily_loss' => 'risk.max_daily_loss',
            'trading.risk.max_daily_profit' => 'risk.max_daily_profit',
            'trading.risk.trading_sessions' => 'risk.trading_sessions',
            'trading.ai_entry.strategy' => 'ai_entry.strategy',
            'trading.ai_entry.min_risk_reward' => 'ai_entry.min_risk_reward',
            'trading.ai_entry.recent_candles' => 'ai_entry.recent_candles',
            'trading.pre_filter.enabled' => 'pre_filter.enabled',
            'trading.pre_filter.min_adx' => 'pre_filter.min_adx',
            'trading.pre_filter.min_confluence_factors' => 'pre_filter.min_confluence_factors',
            'trading.pre_filter.skip_neutral_setups' => 'pre_filter.skip_neutral_setups',
            'trading.pre_filter.max_spread_multiplier' => 'pre_filter.max_spread_multiplier',
            'trading.pre_filter.max_spread_points' => 'pre_filter.max_spread_points',
            'trading.signal_validator.max_entry_slippage_points' => 'signal_validator.max_entry_slippage_points',
            'trading.news.enabled' => 'news.enabled',
            'trading.news.calendar_url' => 'news.calendar_url',
            'trading.news.block_minutes_before' => 'news.block_minutes_before',
            'trading.news.block_minutes_after' => 'news.block_minutes_after',
            'trading.news.lookahead_hours' => 'news.lookahead_hours',
            'trading.news.cache_minutes' => 'news.cache_minutes',
            'trading.telegram.enabled' => 'telegram.enabled',
            'trading.telegram.bot_token' => 'telegram.bot_token',
            'trading.telegram.chat_id' => 'telegram.chat_id',
            'trading.telegram.daily_summary_time' => 'telegram.daily_summary_time',
            'trading.telegram.notify.signals' => 'telegram.notify.signals',
            'trading.telegram.notify.rejections' => 'telegram.notify.rejections',
            'trading.telegram.notify.trades' => 'telegram.notify.trades',
            'trading.telegram.notify.backtests' => 'telegram.notify.backtests',
            'trading.telegram.notify.daily_summary' => 'telegram.notify.daily_summary',
            'trading.backtest.max_bars_open' => 'backtest.max_bars_open',
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
            'ai_consensus_enabled' => config('trading.ai.consensus.enabled'),
            'ai_consensus_providers' => implode(', ', config('trading.ai.consensus.providers', [])),
            'ai_consensus_min_agree' => config('trading.ai.consensus.min_agree'),
            'max_risk_per_trade_pct' => config('trading.risk.max_risk_per_trade_pct'),
            'min_confidence' => config('trading.risk.min_confidence'),
            'max_open_trades' => config('trading.risk.max_open_trades'),
            'max_daily_drawdown_pct' => config('trading.risk.max_daily_drawdown_pct'),
            'max_daily_loss' => config('trading.risk.max_daily_loss'),
            'max_daily_profit' => config('trading.risk.max_daily_profit'),
            'trading_sessions' => config('trading.risk.trading_sessions'),
            'ai_entry_strategy' => config('trading.ai_entry.strategy'),
            'ai_min_risk_reward' => config('trading.ai_entry.min_risk_reward'),
            'ai_recent_candles' => config('trading.ai_entry.recent_candles'),
            'pre_filter_enabled' => config('trading.pre_filter.enabled'),
            'pre_filter_min_adx' => config('trading.pre_filter.min_adx'),
            'pre_filter_min_confluence' => config('trading.pre_filter.min_confluence_factors'),
            'pre_filter_skip_neutral' => config('trading.pre_filter.skip_neutral_setups'),
            'pre_filter_max_spread_mult' => config('trading.pre_filter.max_spread_multiplier'),
            'pre_filter_max_spread_points' => config('trading.pre_filter.max_spread_points'),
            'signal_max_entry_slippage_points' => config('trading.signal_validator.max_entry_slippage_points'),
            'news_enabled' => config('trading.news.enabled'),
            'news_calendar_url' => config('trading.news.calendar_url'),
            'news_block_minutes_before' => config('trading.news.block_minutes_before'),
            'news_block_minutes_after' => config('trading.news.block_minutes_after'),
            'news_lookahead_hours' => config('trading.news.lookahead_hours'),
            'news_cache_minutes' => config('trading.news.cache_minutes'),
            'telegram_enabled' => config('trading.telegram.enabled'),
            'telegram_configured' => Setting::hasValue('trading.telegram.bot_token'),
            'telegram_chat_id' => config('trading.telegram.chat_id'),
            'telegram_daily_summary_time' => config('trading.telegram.daily_summary_time'),
            'telegram_notify_signals' => config('trading.telegram.notify.signals'),
            'telegram_notify_rejections' => config('trading.telegram.notify.rejections'),
            'telegram_notify_trades' => config('trading.telegram.notify.trades'),
            'telegram_notify_backtests' => config('trading.telegram.notify.backtests'),
            'telegram_notify_daily_summary' => config('trading.telegram.notify.daily_summary'),
            'backtest_max_bars_open' => config('trading.backtest.max_bars_open'),
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

        Setting::setValue('trading.ai.consensus.enabled', $this->boolString($input['ai_consensus_enabled'] ?? false));
        Setting::setValue('trading.ai.consensus.providers', $input['ai_consensus_providers'] ?? 'openai,anthropic,gemini');
        Setting::setValue('trading.ai.consensus.min_agree', (string) ($input['ai_consensus_min_agree'] ?? 2));

        Setting::setValue('trading.risk.max_risk_per_trade_pct', (string) $input['max_risk_per_trade_pct']);
        Setting::setValue('trading.risk.min_confidence', (string) $input['min_confidence']);
        Setting::setValue('trading.risk.max_open_trades', (string) $input['max_open_trades']);
        Setting::setValue('trading.risk.max_daily_drawdown_pct', (string) $input['max_daily_drawdown_pct']);
        Setting::setValue('trading.risk.trading_sessions', $input['trading_sessions']);
        Setting::setValue('trading.risk.max_daily_loss', filled($input['max_daily_loss'] ?? null) ? (string) $input['max_daily_loss'] : null);
        Setting::setValue('trading.risk.max_daily_profit', filled($input['max_daily_profit'] ?? null) ? (string) $input['max_daily_profit'] : null);

        Setting::setValue('trading.ai_entry.strategy', $input['ai_entry_strategy'] ?? 'balanced');
        Setting::setValue('trading.ai_entry.min_risk_reward', (string) ($input['ai_min_risk_reward'] ?? 2));
        Setting::setValue('trading.ai_entry.recent_candles', (string) ($input['ai_recent_candles'] ?? 10));

        Setting::setValue('trading.pre_filter.enabled', $this->boolString($input['pre_filter_enabled'] ?? true));
        Setting::setValue('trading.pre_filter.min_adx', (string) ($input['pre_filter_min_adx'] ?? 15));
        Setting::setValue('trading.pre_filter.min_confluence_factors', (string) ($input['pre_filter_min_confluence'] ?? 2));
        Setting::setValue('trading.pre_filter.skip_neutral_setups', $this->boolString($input['pre_filter_skip_neutral'] ?? true));
        Setting::setValue('trading.pre_filter.max_spread_multiplier', (string) ($input['pre_filter_max_spread_mult'] ?? 3));
        Setting::setValue('trading.pre_filter.max_spread_points', (string) ($input['pre_filter_max_spread_points'] ?? 0));

        Setting::setValue('trading.signal_validator.max_entry_slippage_points', (string) ($input['signal_max_entry_slippage_points'] ?? 50));

        Setting::setValue('trading.news.enabled', $this->boolString($input['news_enabled'] ?? true));
        Setting::setValue('trading.news.calendar_url', $input['news_calendar_url'] ?? config('trading.news.calendar_url'));
        Setting::setValue('trading.news.block_minutes_before', (string) ($input['news_block_minutes_before'] ?? 30));
        Setting::setValue('trading.news.block_minutes_after', (string) ($input['news_block_minutes_after'] ?? 15));
        Setting::setValue('trading.news.lookahead_hours', (string) ($input['news_lookahead_hours'] ?? 8));
        Setting::setValue('trading.news.cache_minutes', (string) ($input['news_cache_minutes'] ?? 60));

        Setting::setValue('trading.telegram.enabled', $this->boolString($input['telegram_enabled'] ?? false));
        if (! empty($input['telegram_bot_token'])) {
            Setting::setValue('trading.telegram.bot_token', $input['telegram_bot_token']);
        }
        if ($input['clear_telegram_bot_token'] ?? false) {
            Setting::setValue('trading.telegram.bot_token', null);
        }
        Setting::setValue('trading.telegram.chat_id', filled($input['telegram_chat_id'] ?? null) ? (string) $input['telegram_chat_id'] : null);
        Setting::setValue('trading.telegram.daily_summary_time', $input['telegram_daily_summary_time'] ?? '20:00');
        Setting::setValue('trading.telegram.notify.signals', $this->boolString($input['telegram_notify_signals'] ?? true));
        Setting::setValue('trading.telegram.notify.rejections', $this->boolString($input['telegram_notify_rejections'] ?? false));
        Setting::setValue('trading.telegram.notify.trades', $this->boolString($input['telegram_notify_trades'] ?? true));
        Setting::setValue('trading.telegram.notify.backtests', $this->boolString($input['telegram_notify_backtests'] ?? true));
        Setting::setValue('trading.telegram.notify.daily_summary', $this->boolString($input['telegram_notify_daily_summary'] ?? true));

        Setting::setValue('trading.backtest.max_bars_open', (string) ($input['backtest_max_bars_open'] ?? 96));

        $this->applyToConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return array_merge([
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
        ], $this->pipelineValidationRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function pipelineValidationRules(): array
    {
        return [
            'ai_consensus_enabled' => ['nullable', 'boolean'],
            'ai_consensus_providers' => ['nullable', 'string', 'max:120'],
            'ai_consensus_min_agree' => ['nullable', 'integer', 'min:1', 'max:3'],
            'ai_entry_strategy' => ['nullable', 'string', 'in:conservative,balanced,active'],
            'ai_min_risk_reward' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'ai_recent_candles' => ['nullable', 'integer', 'min:5', 'max:50'],
            'pre_filter_enabled' => ['nullable', 'boolean'],
            'pre_filter_min_adx' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_filter_min_confluence' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pre_filter_skip_neutral' => ['nullable', 'boolean'],
            'pre_filter_max_spread_mult' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'pre_filter_max_spread_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'signal_max_entry_slippage_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'news_enabled' => ['nullable', 'boolean'],
            'news_calendar_url' => ['nullable', 'string', 'max:500'],
            'news_block_minutes_before' => ['nullable', 'integer', 'min:0', 'max:240'],
            'news_block_minutes_after' => ['nullable', 'integer', 'min:0', 'max:240'],
            'news_lookahead_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
            'news_cache_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'telegram_enabled' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'clear_telegram_bot_token' => ['nullable', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'telegram_daily_summary_time' => ['nullable', 'string', 'max:8'],
            'telegram_notify_signals' => ['nullable', 'boolean'],
            'telegram_notify_rejections' => ['nullable', 'boolean'],
            'telegram_notify_trades' => ['nullable', 'boolean'],
            'telegram_notify_backtests' => ['nullable', 'boolean'],
            'telegram_notify_daily_summary' => ['nullable', 'boolean'],
            'backtest_max_bars_open' => ['nullable', 'integer', 'min:10', 'max:500'],
        ];
    }

    private function castValue(string $configPath, string $value): mixed
    {
        return match ($configPath) {
            'symbols' => array_values(array_filter(array_map('trim', explode(',', $value)))),
            'ai.consensus.providers' => array_values(array_filter(array_map('trim', explode(',', $value)))),
            'candle_count', 'risk.min_confidence', 'risk.max_open_trades', 'ai.consensus.min_agree',
            'ai_entry.recent_candles', 'pre_filter.min_confluence_factors', 'pre_filter.max_spread_points',
            'signal_validator.max_entry_slippage_points', 'news.block_minutes_before', 'news.block_minutes_after',
            'news.lookahead_hours', 'news.cache_minutes', 'backtest.max_bars_open' => (int) $value,
            'risk.max_risk_per_trade_pct', 'risk.max_daily_drawdown_pct', 'ai_entry.min_risk_reward',
            'pre_filter.min_adx', 'pre_filter.max_spread_multiplier' => (float) $value,
            'risk.max_daily_loss', 'risk.max_daily_profit' => $value === '' ? null : (float) $value,
            'ai.consensus.enabled', 'pre_filter.enabled', 'pre_filter.skip_neutral_setups', 'news.enabled',
            'telegram.enabled', 'telegram.notify.signals', 'telegram.notify.rejections',
            'telegram.notify.trades', 'telegram.notify.backtests', 'telegram.notify.daily_summary' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }

    private function boolString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
    }
}
