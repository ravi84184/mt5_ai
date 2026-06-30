<?php

namespace Tests\Unit;

use App\Models\Signal;
use App\Services\PreTradeFilterService;
use App\Services\SignalValidatorService;
use Tests\TestCase;

class TradingPipelineServicesTest extends TestCase
{
    public function test_pre_filter_skips_choppy_market(): void
    {
        config(['trading.pre_filter.enabled' => true, 'trading.news.enabled' => false]);

        $service = new PreTradeFilterService;
        $reason = $service->getSkipReason($this->baseSymbolData(), [
            'analysis' => [
                'adx' => ['value' => 12],
                'volatility' => ['regime' => 'compressed'],
                'multi_timeframe' => ['alignment' => 'mixed'],
                'confluence' => ['bias' => 'neutral', 'bullish_factors' => 1, 'bearish_factors' => 1],
            ],
        ]);

        $this->assertNotNull($reason);
    }

    public function test_signal_validator_rejects_low_risk_reward(): void
    {
        config(['trading.ai_entry.min_risk_reward' => 2.0]);

        $validator = new SignalValidatorService;
        $signal = new Signal(['action' => 'BUY', 'entry_price' => 100, 'stop_loss' => 95, 'take_profit' => 104]);

        $this->assertNotNull($validator->getRejectionReason($signal, $this->baseSymbolData()));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSymbolData(): array
    {
        return [
            'symbol' => 'XAUUSD',
            'market' => ['bid' => 3350, 'ask' => 3350.5, 'spread' => 5],
            'symbol_info' => [
                'digits' => 2, 'point' => 0.01,
                'min_stop_distance' => 0.5, 'min_stop_distance_points' => 50,
                'typical_spread_points' => 5,
            ],
        ];
    }
}
