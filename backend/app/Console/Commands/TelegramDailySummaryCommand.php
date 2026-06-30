<?php

namespace App\Console\Commands;

use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Console\Command;

class TelegramDailySummaryCommand extends Command
{
    protected $signature = 'trading:telegram-daily-summary';

    protected $description = 'Send daily trading summary via Telegram';

    public function handle(TelegramNotificationService $telegram): int
    {
        if (! $telegram->isEnabled()) {
            $this->warn('Telegram not enabled — configure in Admin → System → Trading settings.');

            return self::SUCCESS;
        }

        $telegram->sendDailySummary();
        $this->info('Daily summary sent.');

        return self::SUCCESS;
    }
}
