<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('admin_authenticated')) {
            return redirect()->route('admin.overview');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $password = config('admin.password');

        if (! $password) {
            return back()->withErrors([
                'password' => 'Admin password is not configured. Set ADMIN_PASSWORD in .env.',
            ]);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! hash_equals($password, $request->input('password'))) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        session(['admin_authenticated' => true]);

        return redirect()->route('admin.overview');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');

        return redirect()->route('admin.login');
    }
}
