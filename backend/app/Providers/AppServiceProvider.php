<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerDashboardRoutesIfMissing();
    }

    private function registerDashboardRoutesIfMissing(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $hasDashboard = collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'dashboard')
        );

        if ($hasDashboard) {
            return;
        }

        Route::redirect('/', '/dashboard');

        Route::prefix('dashboard')
            ->name('dashboard.')
            ->group(base_path('routes/dashboard.php'));
    }
}
