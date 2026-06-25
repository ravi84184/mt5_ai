<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_config_returns_defaults_for_unknown_account(): void
    {
        config(['trading.api_token' => 'test-token']);

        $response = $this->getJson('/api/account-config?account=999999', [
            'X-API-TOKEN' => 'test-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'symbols' => [],
                'trading_enabled' => false,
                'configured' => false,
            ]);
    }

    public function test_account_config_returns_admin_settings(): void
    {
        config(['trading.api_token' => 'test-token']);

        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
            'ai_provider' => 'anthropic',
            'symbols' => ['XAUUSD', 'EURUSD'],
            'trading_enabled' => true,
            'min_confidence' => 85,
        ]);

        $response = $this->getJson('/api/account-config?account=104392039', [
            'X-API-TOKEN' => 'test-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'mt5_login' => 104392039,
                'ai_provider' => 'anthropic',
                'symbols' => ['XAUUSD', 'EURUSD'],
                'trading_enabled' => true,
                'min_confidence' => 85,
                'configured' => true,
            ]);
    }
}
