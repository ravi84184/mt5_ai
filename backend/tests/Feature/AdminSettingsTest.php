<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\TradingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.password' => 'test-admin-secret']);
    }

    private function asAdmin(): static
    {
        return $this->withSession(['admin_authenticated' => true]);
    }

    public function test_admin_can_view_trading_settings_page(): void
    {
        $this->asAdmin()
            ->get(route('admin.system.settings'))
            ->assertOk()
            ->assertSee('Trading settings')
            ->assertSee('OpenAI');
    }

    public function test_admin_can_save_trading_settings(): void
    {
        $this->asAdmin()
            ->put(route('admin.system.settings.update'), [
                'symbols' => 'XAUUSD, EURUSD',
                'candle_count' => 60,
                'ai_provider' => 'openai',
                'openai_api_key' => 'sk-test-openai-key',
                'openai_model' => 'gpt-4o-mini',
                'anthropic_api_key' => '',
                'anthropic_model' => 'claude-sonnet-4-6',
                'gemini_api_key' => '',
                'gemini_model' => 'gemini-2.0-flash',
                'max_risk_per_trade_pct' => 1.5,
                'min_confidence' => 85,
                'max_open_trades' => 4,
                'max_daily_drawdown_pct' => 2.5,
                'max_daily_loss' => '',
                'max_daily_profit' => '',
                'trading_sessions' => '08:00-18:00',
            ])
            ->assertRedirect(route('admin.system.settings'));

        app(TradingSettingsService::class)->applyToConfig();

        $this->assertSame(['XAUUSD', 'EURUSD'], config('trading.symbols'));
        $this->assertSame(60, config('trading.candle_count'));
        $this->assertSame('openai', config('trading.ai.provider'));
        $this->assertSame('sk-test-openai-key', config('trading.ai.openai.api_key'));
        $this->assertSame(85, config('trading.risk.min_confidence'));
        $this->assertSame('08:00-18:00', config('trading.risk.trading_sessions'));
        $this->assertTrue(Setting::hasValue('trading.ai.openai.api_key'));
    }

    public function test_api_key_is_not_overwritten_when_left_blank(): void
    {
        Setting::setValue('trading.ai.openai.api_key', 'sk-existing-key');

        $this->asAdmin()
            ->put(route('admin.system.settings.update'), [
                'symbols' => 'XAUUSD',
                'candle_count' => 50,
                'ai_provider' => 'openai',
                'openai_api_key' => '',
                'openai_model' => 'gpt-4o-mini',
                'anthropic_model' => 'claude-sonnet-4-6',
                'gemini_model' => 'gemini-2.0-flash',
                'max_risk_per_trade_pct' => 1,
                'min_confidence' => 80,
                'max_open_trades' => 3,
                'max_daily_drawdown_pct' => 3,
                'trading_sessions' => '00:00-23:59',
            ])
            ->assertRedirect();

        app(TradingSettingsService::class)->applyToConfig();

        $this->assertSame('sk-existing-key', config('trading.ai.openai.api_key'));
    }
}
