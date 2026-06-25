<?php

use App\Http\Controllers\Dashboard\DashboardAuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('login', [DashboardAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [DashboardAuthController::class, 'login'])->name('login.submit');

    Route::middleware('dashboard.auth')->group(function () {
        Route::post('logout', [DashboardAuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('accounts', [DashboardController::class, 'accounts'])->name('accounts');
        Route::get('signals', [DashboardController::class, 'signals'])->name('signals');
        Route::get('trades', [DashboardController::class, 'trades'])->name('trades');
        Route::get('ai-logs', [DashboardController::class, 'aiLogs'])->name('ai-logs');
        Route::get('ai-logs/{aiLog}', [DashboardController::class, 'aiLogShow'])->name('ai-logs.show');
    });
});
