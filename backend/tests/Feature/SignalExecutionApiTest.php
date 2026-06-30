<?php

namespace Tests\Feature;

use App\Enums\SignalStatus;
use App\Models\Account;
use App\Models\Signal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalExecutionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithToken(): array
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

        return [$account, $token];
    }

    public function test_signals_endpoint_returns_pending_buy_signal(): void
    {
        [$account, $token] = $this->createAccountWithToken();

        Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'entry_price' => 3365.5,
            'stop_loss' => 3350,
            'take_profit' => 3395,
            'confidence' => 88,
            'status' => SignalStatus::Pending,
            'ai_provider' => 'openai',
        ]);

        $this->getJson('/api/signals?account=104392039', [
            'X-API-TOKEN' => $token,
        ])->assertOk()
            ->assertJsonPath('action', 'BUY')
            ->assertJsonPath('symbol', 'XAUUSD')
            ->assertJsonPath('confidence', 88);
    }

    public function test_rejected_signals_are_not_returned_to_ea(): void
    {
        [$account, $token] = $this->createAccountWithToken();

        Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'confidence' => 88,
            'status' => SignalStatus::Rejected,
            'rejection_reason' => 'Signal validator: test',
            'ai_provider' => 'openai',
        ]);

        $this->getJson('/api/signals?account=104392039', [
            'X-API-TOKEN' => $token,
        ])->assertOk()
            ->assertJson(['status' => 'NO_SIGNAL']);
    }

    public function test_ea_can_report_signal_execution_failure(): void
    {
        [$account, $token] = $this->createAccountWithToken();

        $signal = Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'entry_price' => 3365.5,
            'stop_loss' => 3350,
            'take_profit' => 3395,
            'confidence' => 88,
            'status' => SignalStatus::Pending,
            'ai_provider' => 'openai',
        ]);

        $this->postJson('/api/signals/failed', [
            'signal_id' => $signal->id,
            'reason' => 'Invalid SL/TP for broker minimum stop distance',
        ], [
            'X-API-TOKEN' => $token,
        ])->assertOk();

        $signal->refresh();
        $this->assertSame(SignalStatus::Rejected, $signal->status);
        $this->assertStringContainsString('Invalid SL/TP', $signal->rejection_reason);
    }

    public function test_executed_endpoint_is_idempotent_for_already_executed_signal(): void
    {
        [$account, $token] = $this->createAccountWithToken();

        $signal = Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'entry_price' => 3365.5,
            'stop_loss' => 3350,
            'take_profit' => 3395,
            'confidence' => 88,
            'status' => SignalStatus::Executed,
            'ticket' => 12345,
            'ai_provider' => 'openai',
        ]);

        $this->postJson('/api/signals/executed', [
            'signal_id' => $signal->id,
            'ticket' => 12345,
            'status' => 'EXECUTED',
            'symbol' => 'XAUUSD',
            'type' => 'BUY',
            'lot' => 0.1,
            'entry_price' => 3365.5,
        ], [
            'X-API-TOKEN' => $token,
        ])->assertOk();
    }
}
