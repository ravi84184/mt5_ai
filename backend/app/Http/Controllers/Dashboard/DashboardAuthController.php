<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('dashboard_authenticated')) {
            return redirect()->route('dashboard.index');
        }

        return view('dashboard.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $password = config('dashboard.password');

        if (! $password) {
            return back()->withErrors([
                'password' => 'Dashboard password is not configured. Set DASHBOARD_PASSWORD in .env.',
            ]);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! hash_equals($password, $request->input('password'))) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        session(['dashboard_authenticated' => true]);

        return redirect()->route('dashboard.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('dashboard_authenticated');

        return redirect()->route('dashboard.login');
    }
}
