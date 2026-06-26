<?php

namespace Tests\Unit;

use App\Services\AI\PromptBuilder;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    public function test_entry_system_prompt_defines_analysis_framework(): void
    {
        $prompt = PromptBuilder::entrySystemPrompt();

        $this->assertStringContainsString('EMA stack', $prompt);
        $this->assertStringContainsString('risk.min_confidence', $prompt);
        $this->assertStringContainsString('WAIT', $prompt);
    }

    public function test_entry_user_prompt_includes_risk_constraints_and_summary(): void
    {
        $prompt = PromptBuilder::entryUserPrompt([
            'account' => ['balance' => 10000, 'equity' => 10000],
            'symbol' => [
                'symbol' => 'XAUUSD',
                'timeframe' => 'M15',
                'indicators' => [
                    'ema20' => 3350,
                    'ema50' => 3340,
                    'ema200' => 3300,
                    'rsi' => 58,
                    'atr' => 12,
                ],
                'candles' => [
                    ['close' => 3340],
                    ['close' => 3358],
                ],
            ],
            'risk' => [
                'min_confidence' => 85,
                'max_risk_per_trade_pct' => 1.5,
            ],
        ]);

        $this->assertStringContainsString('Minimum confidence for executable BUY/SELL: 85', $prompt);
        $this->assertStringContainsString('Latest closed price: 3358', $prompt);
        $this->assertStringContainsString('XAUUSD', $prompt);
    }

    public function test_position_user_prompt_summarizes_open_trade(): void
    {
        $prompt = PromptBuilder::positionUserPrompt([
            'ticket' => 123456,
            'position' => [
                'symbol' => 'XAUUSD',
                'type' => 'BUY',
                'entry_price' => 3350,
                'current_price' => 3365,
                'profit' => 150,
                'sl' => 3340,
                'tp' => 3380,
                'duration_minutes' => 45,
            ],
            'market_data' => ['candles' => []],
        ]);

        $this->assertStringContainsString('position #123456', $prompt);
        $this->assertStringContainsString('Side: BUY', $prompt);
        $this->assertStringContainsString('P&L: 150', $prompt);
        $this->assertStringContainsString('MOVE_TO_BREAKEVEN', $prompt);
    }
}
