<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('trading:reset-daily-pnl')->dailyAt('00:05');
        $schedule->command('trading:telegram-daily-summary')->dailyAt(
            config('trading.telegram.daily_summary_time', '20:00')
        );
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mt5.token' => \App\Http\Middleware\VerifyMt5ApiToken::class,
            'admin.auth' => \App\Http\Middleware\VerifyAdminAuth::class,
            'dashboard.auth' => \App\Http\Middleware\VerifyAdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
