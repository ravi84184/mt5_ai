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

        config(['dashboard.password' => 'test-dashboard-secret']);
    }

    public function test_admin_can_update_account_settings(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $this->withSession(['dashboard_authenticated' => true])
            ->put(route('dashboard.accounts.update', $account), [
                'ai_provider' => 'gemini',
                'symbols' => 'XAUUSD, EURUSD',
                'trading_enabled' => '1',
                'min_confidence' => 88,
                'max_open_trades' => 2,
            ])
            ->assertRedirect(route('dashboard.accounts.edit', $account));

        $account->refresh();

        $this->assertSame('gemini', $account->ai_provider);
        $this->assertSame(['XAUUSD', 'EURUSD'], $account->symbols);
        $this->assertTrue($account->trading_enabled);
        $this->assertSame(88, $account->min_confidence);
        $this->assertSame(2, $account->max_open_trades);
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

        $this->withSession(['dashboard_authenticated' => true])
            ->post(route('dashboard.signals.store', $account), [
                'symbol' => 'XAUUSD',
                'action' => 'BUY',
                'confidence' => 92,
                'entry_price' => 3350,
                'stop_loss' => 3340,
                'take_profit' => 3370,
            ])
            ->assertRedirect(route('dashboard.signals'));

        $this->assertDatabaseHas('signals', [
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'status' => SignalStatus::Pending->value,
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

        $this->withSession(['dashboard_authenticated' => true])
            ->post(route('dashboard.trades.close', $trade), [
                'reason' => 'Admin close test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('position_management_decisions', [
            'ticket' => 123456,
            'account_id' => $account->id,
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

        $this->withSession(['dashboard_authenticated' => true])
            ->post(route('dashboard.signals.cancel', $signal))
            ->assertRedirect();

        $signal->refresh();
        $this->assertSame(SignalStatus::Rejected, $signal->status);
    }
}
