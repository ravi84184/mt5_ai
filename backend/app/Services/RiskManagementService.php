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
        return $this->getRejectionReason($account, $signal) === null;
    }

    public function getRejectionReason(Account $account, Signal $signal): ?string
    {
        $config = config('trading.risk');
        $minConfidence = $account->resolvedMinConfidence();
        $maxOpenTrades = $account->resolvedMaxOpenTrades();

        if (! $account->isTradingEnabled()) {
            return 'Trading disabled for this account by admin';
        }

        if ($account->hasSymbolRestrictions() && ! $account->isSymbolAllowed($signal->symbol)) {
            return "Symbol {$signal->symbol} is not enabled for this account";
        }

        if ($signal->confidence < $minConfidence) {
            return "Confidence {$signal->confidence} below minimum {$minConfidence}";
        }

        if (! in_array($signal->action, ['BUY', 'SELL'], true)) {
            return "Action {$signal->action} is not tradable (only BUY/SELL)";
        }

        $openTrades = Trade::where('account_id', $account->id)
            ->where('status', 'OPEN')
            ->count();

        if ($openTrades >= $maxOpenTrades) {
            return "Max open trades reached ({$openTrades}/{$maxOpenTrades})";
        }

        if (! $this->withinTradingSession($config['trading_sessions'])) {
            $now = Carbon::now()->format('H:i T');

            return "Outside trading session (server time {$now}, allowed: {$config['trading_sessions']})";
        }

        if (! $this->withinDailyLimits($account, $config)) {
            return 'Daily loss/drawdown limit reached';
        }

        return null;
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
