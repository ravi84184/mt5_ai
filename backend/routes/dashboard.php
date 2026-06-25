<?php

use App\Http\Controllers\Dashboard\DashboardAccountController;
use App\Http\Controllers\Dashboard\DashboardAdminController;
use App\Http\Controllers\Dashboard\DashboardAuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('login', [DashboardAuthController::class, 'showLogin'])->name('login');
Route::post('login', [DashboardAuthController::class, 'login'])->name('login.submit');

Route::middleware('dashboard.auth')->group(function () {
    Route::post('logout', [DashboardAuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('accounts', [DashboardController::class, 'accounts'])->name('accounts');
    Route::get('accounts/{account}/edit', [DashboardAccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [DashboardAccountController::class, 'update'])->name('accounts.update');
    Route::get('signals', [DashboardController::class, 'signals'])->name('signals');
    Route::get('accounts/{account}/signals/create', [DashboardAdminController::class, 'createSignal'])->name('signals.create');
    Route::post('accounts/{account}/signals', [DashboardAdminController::class, 'storeSignal'])->name('signals.store');
    Route::post('signals/{signal}/cancel', [DashboardAdminController::class, 'cancelSignal'])->name('signals.cancel');
    Route::get('trades', [DashboardController::class, 'trades'])->name('trades');
    Route::get('trades/{trade}/edit', [DashboardAdminController::class, 'editTrade'])->name('trades.edit');
    Route::put('trades/{trade}', [DashboardAdminController::class, 'updateTrade'])->name('trades.update');
    Route::post('trades/{trade}/close', [DashboardAdminController::class, 'closeTrade'])->name('trades.close');
    Route::post('trades/{trade}/modify-sl', [DashboardAdminController::class, 'modifyTradeSl'])->name('trades.modify-sl');
    Route::get('ai-logs', [DashboardController::class, 'aiLogs'])->name('ai-logs');
    Route::get('ai-logs/{aiLog}', [DashboardController::class, 'aiLogShow'])->name('ai-logs.show');
});
