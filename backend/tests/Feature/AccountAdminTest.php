<?php

namespace Tests\Feature;

use App\Enums\SignalStatus;
use App\Models\Account;
use App\Models\Signal;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAdminTest extends TestCase
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

    public function test_admin_can_update_account_settings(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $this->asAdmin()
            ->put(route('admin.accounts.update', $account), [
                'ai_provider' => 'gemini',
                'symbols' => 'XAUUSD, EURUSD',
                'trading_enabled' => '1',
                'min_confidence' => 88,
            ])
            ->assertRedirect(route('admin.accounts.show', $account));

        $account->refresh();
        $this->assertSame('gemini', $account->ai_provider);
        $this->assertSame(['XAUUSD', 'EURUSD'], $account->symbols);
    }

    public function test_admin_can_create_manual_signal(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
            'symbols' => ['XAUUSD'],
            'trading_enabled' => true,
        ]);

        $this->asAdmin()
            ->post(route('admin.signals.store', $account), [
                'symbol' => 'XAUUSD',
                'action' => 'BUY',
                'confidence' => 92,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('signals', [
            'account_id' => $account->id,
            'action' => 'BUY',
            'ai_provider' => 'admin',
        ]);
    }

    public function test_admin_can_queue_trade_close(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $trade = Trade::create([
            'ticket' => 123456,
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'type' => 'BUY',
            'lot' => 0.1,
            'entry_price' => 3350,
            'status' => 'OPEN',
        ]);

        $this->asAdmin()
            ->post(route('admin.trades.close', $trade))
            ->assertRedirect();

        $this->assertDatabaseHas('position_management_decisions', [
            'ticket' => 123456,
            'action' => 'CLOSE',
            'status' => 'PENDING',
        ]);
    }

    public function test_admin_can_cancel_pending_signal(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $signal = Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'confidence' => 90,
            'status' => SignalStatus::Pending,
        ]);

        $this->asAdmin()
            ->post(route('admin.signals.cancel', $signal))
            ->assertRedirect();

        $signal->refresh();
        $this->assertSame(SignalStatus::Rejected, $signal->status);
    }
}
