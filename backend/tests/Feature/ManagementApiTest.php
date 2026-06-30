<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PositionManagementDecision;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_endpoint_returns_pending_sl_update_with_symbol(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $token = $account->generateApiToken();

        Trade::create([
            'ticket' => 555001,
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'type' => 'BUY',
            'lot' => 0.1,
            'entry_price' => 3360,
            'status' => 'OPEN',
        ]);

        PositionManagementDecision::create([
            'ticket' => 555001,
            'account_id' => $account->id,
            'action' => 'MOVE_SL',
            'new_sl' => 3350,
            'reason' => 'Admin SL update',
            'status' => 'PENDING',
        ]);

        $this->getJson('/api/signals/management?account=104392039', [
            'X-API-TOKEN' => $token,
        ])->assertOk()
            ->assertJsonPath('action', 'MOVE_SL')
            ->assertJsonPath('symbol', 'XAUUSD')
            ->assertJsonPath('new_sl', 3350);

        $this->assertDatabaseHas('position_management_decisions', [
            'ticket' => 555001,
            'status' => 'FETCHED',
        ]);
    }

    public function test_management_applied_updates_decision_by_id(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        $token = $account->generateApiToken();

        Trade::create([
            'ticket' => 555001,
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'type' => 'BUY',
            'lot' => 0.1,
            'entry_price' => 3360,
            'status' => 'OPEN',
        ]);

        $decision = PositionManagementDecision::create([
            'ticket' => 555001,
            'account_id' => $account->id,
            'action' => 'MOVE_SL',
            'new_sl' => 3350,
            'status' => 'FETCHED',
        ]);

        $this->postJson('/api/signals/management/applied', [
            'decision_id' => $decision->id,
            'ticket' => 555001,
            'position_ticket' => 777888,
            'action' => 'MOVE_SL',
            'status' => 'APPLIED',
        ], [
            'X-API-TOKEN' => $token,
        ])->assertOk();

        $decision->refresh();
        $this->assertSame('APPLIED', $decision->status);

        $this->assertDatabaseHas('trades', [
            'account_id' => $account->id,
            'ticket' => 777888,
        ]);
    }
}
