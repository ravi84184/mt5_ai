<?php

namespace App\Console\Commands;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResetDailyPnlCommand extends Command
{
    protected $signature = 'trading:reset-daily-pnl';

    protected $description = 'Reset daily PnL counters for accounts';

    public function handle(): int
    {
        $today = Carbon::today();
        $updated = Account::query()
            ->where(function ($query) use ($today) {
                $query->whereNull('pnl_date')->orWhereDate('pnl_date', '<', $today);
            })
            ->update(['pnl_date' => $today, 'daily_pnl' => 0]);

        $this->info("Reset daily PnL for {$updated} account(s).");

        return self::SUCCESS;
    }
}
