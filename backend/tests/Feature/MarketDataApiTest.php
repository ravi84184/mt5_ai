<?php

namespace Tests\Feature;

use App\Jobs\ProcessMarketAnalysisJob;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_data_endpoint_accepts_payload(): void
    {
        Queue::fake();

        config(['trading.api_token' => 'test-token']);

        $response = $this->postJson('/api/market-data', [
            'account' => [
                'login' => 123456,
                'balance' => 10000,
                'equity' => 10250,
                'free_margin' => 9500,
            ],
            'symbols' => [
                [
                    'symbol' => 'XAUUSD',
                    'timeframe' => 'M15',
                    'indicators' => [
                        'ema20' => 3350,
                        'ema50' => 3340,
                        'ema200' => 3300,
                        'rsi' => 64,
                        'atr' => 12,
                    ],
                    'candles' => [
                        [
                            'time' => '2026-06-24 10:00',
                            'open' => 3350,
                            'high' => 3360,
                            'low' => 3345,
                            'close' => 3358,
                            'volume' => 1000,
                        ],
                    ],
                ],
            ],
        ], [
            'X-API-TOKEN' => 'test-token',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('accounts', ['mt5_login' => 123456]);

        Queue::assertPushed(ProcessMarketAnalysisJob::class, function ($job) {
            return $job->accountId === Account::first()->id;
        });
    }

    public function test_signals_returns_no_signal_when_empty(): void
    {
        config(['trading.api_token' => 'test-token']);

        Account::create([
            'mt5_login' => 999,
            'balance' => 1000,
            'equity' => 1000,
            'free_margin' => 1000,
        ]);

        $response = $this->getJson('/api/signals?account=999', [
            'X-API-TOKEN' => 'test-token',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'NO_SIGNAL']);
    }
}
