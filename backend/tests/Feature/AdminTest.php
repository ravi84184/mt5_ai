<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.password' => 'test-admin-secret']);
    }

    public function test_admin_redirects_to_login_when_unauthenticated(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_works(): void
    {
        $this->post(route('admin.login.submit'), ['password' => 'test-admin-secret'])
            ->assertRedirect(route('admin.overview'));

        $this->get('/admin')->assertOk();
    }

    public function test_dashboard_url_redirects_to_admin(): void
    {
        $this->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_admin_overview_loads(): void
    {
        Account::create([
            'mt5_login' => 104392039,
            'balance' => 10000,
            'equity' => 10000,
            'free_margin' => 9500,
            'symbols' => ['XAUUSD'],
        ]);

        $this->withSession(['admin_authenticated' => true])
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertSee('Super Admin Overview')
            ->assertSee('Win rate (30d)');
    }
}
