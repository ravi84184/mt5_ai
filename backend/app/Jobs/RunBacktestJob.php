<?php

namespace App\Jobs;

use App\Models\BacktestRun;
use App\Services\Backtesting\BacktestEngine;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBacktestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $backtestRunId) {}

    public function handle(BacktestEngine $engine, TelegramNotificationService $telegram): void
    {
        $run = BacktestRun::findOrFail($this->backtestRunId);
        $engine->run($run);

        if ($run->fresh()->status === 'COMPLETED') {
            $telegram->notifyBacktestComplete($run->fresh());
        }
    }
}
