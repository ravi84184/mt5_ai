<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Signal;
use App\Models\Trade;
use Carbon\Carbon;

class RiskManagementService
{
    public function canAcceptSignal(Account $account, Signal $signal): bool
    {
        $config = config('trading.risk');

        if ($signal->confidence < $config['min_confidence']) {
            return false;
        }

        if (! in_array($signal->action, ['BUY', 'SELL'], true)) {
            return false;
        }

        $openTrades = Trade::where('account_id', $account->id)
            ->where('status', 'OPEN')
            ->count();

        if ($openTrades >= $config['max_open_trades']) {
            return false;
        }

        if (! $this->withinTradingSession($config['trading_sessions'])) {
            return false;
        }

        if (! $this->withinDailyLimits($account, $config)) {
            return false;
        }

        return true;
    }

    private function withinTradingSession(string $sessions): bool
    {
        $now = Carbon::now()->format('H:i');

        foreach (explode(',', $sessions) as $session) {
            $session = trim($session);
            if (! str_contains($session, '-')) {
                continue;
            }

            [$start, $end] = array_map('trim', explode('-', $session, 2));
            if ($start <= $now && $now <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function withinDailyLimits(Account $account, array $config): bool
    {
        $today = Carbon::today();
        if ($account->pnl_date?->isSameDay($today) !== true) {
            return true;
        }

        $dailyPnl = (float) $account->daily_pnl;
        $balance = max((float) $account->balance, 1);

        if ($config['max_daily_loss'] !== null && $dailyPnl <= -abs($config['max_daily_loss'])) {
            return false;
        }

        if ($config['max_daily_profit'] !== null && $dailyPnl >= abs($config['max_daily_profit'])) {
            return false;
        }

        $drawdownPct = abs(min(0, $dailyPnl)) / $balance * 100;
        if ($drawdownPct >= $config['max_daily_drawdown_pct']) {
            return false;
        }

        return true;
    }
}
