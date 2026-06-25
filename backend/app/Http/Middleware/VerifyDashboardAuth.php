<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDashboardAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('dashboard_authenticated')) {
            return redirect()->route('dashboard.login');
        }

        return $next($request);
    }
}
