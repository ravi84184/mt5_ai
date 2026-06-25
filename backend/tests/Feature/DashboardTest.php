<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\Signal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dashboard.password' => 'test-dashboard-secret']);
    }

    public function test_dashboard_redirects_to_login_when_unauthenticated(): void
    {
        $this->get('/dashboard')
            ->assertRedirect(route('dashboard.login'));
    }

    public function test_dashboard_login_rejects_invalid_password(): void
    {
        $this->post(route('dashboard.login.submit'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
    }

    public function test_dashboard_login_accepts_valid_password(): void
    {
        $this->post(route('dashboard.login.submit'), ['password' => 'test-dashboard-secret'])
            ->assertRedirect(route('dashboard.index'));

        $this->get('/dashboard')->assertOk();
    }

    public function test_dashboard_overview_shows_stats(): void
    {
        $account = Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
        ]);

        Signal::create([
            'account_id' => $account->id,
            'symbol' => 'XAUUSD',
            'action' => 'BUY',
            'confidence' => 85,
            'status' => 'PENDING',
        ]);

        AiInteractionLog::create([
            'account_id' => $account->id,
            'analysis_type' => 'entry',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'symbol' => 'XAUUSD',
            'input_json' => ['symbol' => 'XAUUSD'],
            'system_prompt' => 'system',
            'user_prompt' => 'user',
            'output_json' => ['action' => 'BUY'],
            'status' => 'success',
            'duration_ms' => 1200,
        ]);

        $this->withSession(['dashboard_authenticated' => true])
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('104392039')
            ->assertSee('XAUUSD');
    }

    public function test_dashboard_logout_clears_session(): void
    {
        $this->withSession(['dashboard_authenticated' => true])
            ->post(route('dashboard.logout'))
            ->assertRedirect(route('dashboard.login'));

        $this->get('/dashboard')
            ->assertRedirect(route('dashboard.login'));
    }
}
