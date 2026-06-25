<?php

use App\Enums\AiProvider;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AiLogController;
use App\Http\Controllers\Admin\ManagementController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\SignalController;
use App\Http\Controllers\Admin\SnapshotController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TradeController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('login', [AdminAuthController::class, 'login'])->name('login.submit');

Route::middleware('admin.auth')->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('/', [OverviewController::class, 'index'])->name('overview');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('accounts/{account}/toggle-trading', [AccountController::class, 'toggleTrading'])->name('accounts.toggle-trading');

    Route::get('signals', [SignalController::class, 'index'])->name('signals.index');
    Route::get('signals/{signal}', [SignalController::class, 'show'])->name('signals.show');
    Route::get('accounts/{account}/signals/create', [SignalController::class, 'create'])->name('signals.create');
    Route::post('accounts/{account}/signals', [SignalController::class, 'store'])->name('signals.store');
    Route::post('signals/{signal}/cancel', [SignalController::class, 'cancel'])->name('signals.cancel');

    Route::get('trades', [TradeController::class, 'index'])->name('trades.index');
    Route::get('trades/{trade}/edit', [TradeController::class, 'edit'])->name('trades.edit');
    Route::put('trades/{trade}', [TradeController::class, 'update'])->name('trades.update');
    Route::post('trades/{trade}/close', [TradeController::class, 'close'])->name('trades.close');
    Route::post('trades/{trade}/modify-sl', [TradeController::class, 'modifySl'])->name('trades.modify-sl');

    Route::get('management', [ManagementController::class, 'index'])->name('management.index');
    Route::post('management/{decision}/cancel', [ManagementController::class, 'cancel'])->name('management.cancel');

    Route::get('snapshots', [SnapshotController::class, 'index'])->name('snapshots.index');

    Route::get('ai-logs', [AiLogController::class, 'index'])->name('ai-logs.index');
    Route::get('ai-logs/{aiLog}', [AiLogController::class, 'show'])->name('ai-logs.show');

    Route::get('system', [SystemController::class, 'index'])->name('system.index');
    Route::get('system/queue', [SystemController::class, 'queue'])->name('system.queue');
    Route::post('system/queue/retry-all', [SystemController::class, 'retryAllFailed'])->name('system.queue.retry-all');
    Route::post('system/queue/flush-failed', [SystemController::class, 'flushFailed'])->name('system.queue.flush-failed');
});
