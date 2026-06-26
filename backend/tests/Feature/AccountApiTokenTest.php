<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_account_token_authenticates_and_scopes_requests(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
            'symbols' => ['XAUUSD'],
            'trading_enabled' => true,
        ]);

        $token = $account->generateApiToken();

        $this->getJson('/api/account-config?account=104392039', [
            'X-API-TOKEN' => $token,
        ])->assertOk()
            ->assertJsonPath('mt5_login', 104392039)
            ->assertJsonPath('has_api_token', true);

        $this->getJson('/api/account-config?account=999999', [
            'X-API-TOKEN' => $token,
        ])->assertForbidden();
    }

    public function test_invalid_per_account_token_is_rejected(): void
    {
        config(['trading.api_token' => 'global-only']);

        $this->getJson('/api/account-config?account=1', [
            'X-API-TOKEN' => 'not-a-valid-token',
        ])->assertUnauthorized();
    }

    public function test_global_token_still_works_for_legacy_setups(): void
    {
        config(['trading.api_token' => 'legacy-global-token']);

        Account::create([
            'mt5_login' => 123,
            'balance' => 1000,
            'equity' => 1000,
            'free_margin' => 1000,
        ]);

        $this->getJson('/api/account-config?account=123', [
            'X-API-TOKEN' => 'legacy-global-token',
        ])->assertOk();
    }

    public function test_admin_can_create_account_and_generate_token(): void
    {
        config(['admin.password' => 'test-admin-secret']);

        $response = $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.accounts.store'), [
                'mt5_login' => 104392039,
                'broker' => 'Demo Broker',
                'symbols' => 'XAUUSD',
                'trading_enabled' => '1',
                'generate_api_token' => '1',
            ]);

        $response->assertRedirect();
        $this->assertNotNull(session('api_token'));

        $account = Account::where('mt5_login', 104392039)->first();
        $this->assertTrue($account->hasApiToken());
        $this->assertSame('Demo Broker', $account->broker);

        $this->getJson('/api/account-config?account=104392039', [
            'X-API-TOKEN' => session('api_token'),
        ])->assertOk();
    }

    public function test_admin_can_regenerate_and_revoke_token(): void
    {
        config(['admin.password' => 'test-admin-secret']);

        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $oldToken = $account->generateApiToken();

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.accounts.generate-token', $account))
            ->assertRedirect();

        $newToken = session('api_token');
        $this->assertNotSame($oldToken, $newToken);

        $this->getJson('/api/account-config?account=104392039', [
            'X-API-TOKEN' => $oldToken,
        ])->assertUnauthorized();

        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.accounts.revoke-token', $account))
            ->assertRedirect();

        $account->refresh();
        $this->assertFalse($account->hasApiToken());
    }
}
