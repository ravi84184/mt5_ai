<?php

namespace App\Services\AI;

use App\Models\Account;
use App\Models\Signal;
use App\Models\Trade;

class TradingHistoryContext
{
    /**
     * @return array<string, mixed>
     */
    public static function forAccountAndSymbol(Account $account, string $symbol): array
    {
        $recentTrades = Trade::where('account_id', $account->id)
            ->where('symbol', $symbol)
            ->where('status', 'CLOSED')
            ->latest()
            ->take(30)
            ->get(['profit', 'type', 'created_at']);

        $openTrades = Trade::where('account_id', $account->id)
            ->where('symbol', $symbol)
            ->where('status', 'OPEN')
            ->count();

        $lastSignal = Signal::where('account_id', $account->id)
            ->where('symbol', $symbol)
            ->latest()
            ->first(['action', 'confidence', 'status', 'rejection_reason', 'created_at']);

        $wins = $recentTrades->filter(fn ($t) => (float) $t->profit > 0)->count();
        $losses = $recentTrades->filter(fn ($t) => (float) $t->profit < 0)->count();
        $total = $recentTrades->count();
        $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : null;

        $buyTrades = $recentTrades->where('type', 'BUY');
        $sellTrades = $recentTrades->where('type', 'SELL');

        return [
            'open_trades_on_symbol' => $openTrades,
            'today_account_pnl' => (float) $account->daily_pnl,
            'recent_closed' => [
                'count' => $total,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate_pct' => $winRate,
                'net_profit' => round((float) $recentTrades->sum('profit'), 2),
            ],
            'by_direction' => [
                'buy_win_rate_pct' => $buyTrades->count() > 0
                    ? round($buyTrades->filter(fn ($t) => (float) $t->profit > 0)->count() / $buyTrades->count() * 100, 1)
                    : null,
                'sell_win_rate_pct' => $sellTrades->count() > 0
                    ? round($sellTrades->filter(fn ($t) => (float) $t->profit > 0)->count() / $sellTrades->count() * 100, 1)
                    : null,
            ],
            'last_signal' => $lastSignal ? [
                'action' => $lastSignal->action,
                'confidence' => $lastSignal->confidence,
                'status' => $lastSignal->status->value ?? $lastSignal->status,
                'rejection_reason' => $lastSignal->rejection_reason,
                'at' => $lastSignal->created_at?->toDateTimeString(),
            ] : null,
            'guidance' => self::buildGuidance($winRate, $wins, $losses, $buyTrades, $sellTrades),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Trade>  $buyTrades
     * @param  \Illuminate\Support\Collection<int, Trade>  $sellTrades
     */
    private static function buildGuidance(
        ?float $winRate,
        int $wins,
        int $losses,
        $buyTrades,
        $sellTrades,
    ): string {
        if ($winRate === null) {
            return 'No closed trade history — use standard confluence rules.';
        }

        $parts = ["Symbol win rate {$winRate}% ({$wins}W/{$losses}L)."];

        if ($winRate < 40 && ($wins + $losses) >= 5) {
            $parts[] = 'Underperforming — require stronger confluence and higher confidence.';
        }

        if ($buyTrades->count() >= 3 && $sellTrades->count() >= 3) {
            $buyWr = $buyTrades->filter(fn ($t) => (float) $t->profit > 0)->count() / max(1, $buyTrades->count()) * 100;
            $sellWr = $sellTrades->filter(fn ($t) => (float) $t->profit > 0)->count() / max(1, $sellTrades->count()) * 100;
            if ($buyWr > $sellWr + 20) {
                $parts[] = 'BUY direction has been stronger — favor longs when setup is valid.';
            } elseif ($sellWr > $buyWr + 20) {
                $parts[] = 'SELL direction has been stronger — favor shorts when setup is valid.';
            }
        }

        if ($losses >= 3 && $wins === 0) {
            $parts[] = 'Recent losing streak — avoid revenge trades; prefer WAIT unless setup is exceptional.';
        }

        return implode(' ', $parts);
    }
}
