<?php

use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\Api\PositionAnalysisController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Middleware\VerifyMt5ApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyMt5ApiToken::class)->group(function () {
    Route::post('/market-data', [MarketDataController::class, 'store']);
    Route::get('/signals', [SignalController::class, 'index']);
    Route::post('/signals/executed', [SignalController::class, 'executed']);
    Route::get('/signals/management', [SignalController::class, 'management']);
    Route::post('/signals/management/applied', [SignalController::class, 'managementApplied']);
    Route::post('/position-analysis', [PositionAnalysisController::class, 'store']);
    Route::post('/trades/update', [TradeController::class, 'update']);
});
