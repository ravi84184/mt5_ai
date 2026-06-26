<?php

namespace App\Providers;

use App\Services\TradingSettingsService;
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
        app(TradingSettingsService::class)->applyToConfig();
        $this->registerDashboardRoutesIfMissing();
    }

    private function registerDashboardRoutesIfMissing(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $hasAdmin = collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'admin')
        );

        if ($hasAdmin) {
            return;
        }

        Route::redirect('/', '/admin');

        Route::middleware('web')
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/admin.php'));
    }
}
