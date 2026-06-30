<?php

namespace App\Services\Analytics;

use App\Models\Account;
use App\Models\AiInteractionLog;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TradingAnalyticsService
{
    public function overview(int $days = 30, ?int $accountId = null): array
    {
        $since = Carbon::now()->subDays($days);

        return [
            'period_days' => $days,
            'signals_total' => $this->scoped(Signal::query(), $accountId)->where('created_at', '>=', $since)->count(),
            'signals_pending' => $this->scoped(Signal::query(), $accountId)
                ->where('created_at', '>=', $since)->where('status', 'PENDING')->whereIn('action', ['BUY', 'SELL'])->count(),
            'signals_rejected' => $this->scoped(Signal::query(), $accountId)
                ->where('created_at', '>=', $since)->where('status', 'REJECTED')->count(),
            'trades_closed' => $this->scoped(Trade::query(), $accountId)
                ->where('status', 'CLOSED')->where('updated_at', '>=', $since)->count(),
            'total_profit' => round((float) $this->scoped(Trade::query(), $accountId)
                ->where('status', 'CLOSED')->where('updated_at', '>=', $since)->sum('profit'), 2),
            'win_rate' => $this->winRate($days, $accountId),
            'snapshots' => $this->scoped(MarketSnapshot::query(), $accountId)->where('created_at', '>=', $since)->count(),
            'ai_calls' => $this->scoped(AiInteractionLog::query(), $accountId)->where('created_at', '>=', $since)->count(),
            'queue' => [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function signalBreakdown(int $days = 30, ?int $accountId = null): array
    {
        $since = Carbon::now()->subDays($days);
        $rows = $this->scoped(Signal::query(), $accountId)
            ->where('created_at', '>=', $since)
            ->select('action', DB::raw('count(*) as total'))
            ->groupBy('action')
            ->pluck('total', 'action')
            ->all();

        return [
            'BUY' => (int) ($rows['BUY'] ?? 0),
            'SELL' => (int) ($rows['SELL'] ?? 0),
            'WAIT' => (int) ($rows['WAIT'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function rejectionBreakdown(int $days = 30, ?int $accountId = null): array
    {
        $since = Carbon::now()->subDays($days);
        $signals = $this->scoped(Signal::query(), $accountId)
            ->where('created_at', '>=', $since)
            ->where('status', 'REJECTED')
            ->whereNotNull('rejection_reason')
            ->get(['rejection_reason']);

        $buckets = ['Pre-filter' => 0, 'AI WAIT' => 0, 'Signal validator' => 0, 'Risk rules' => 0, 'Other' => 0];

        foreach ($signals as $signal) {
            $reason = (string) $signal->rejection_reason;
            if (str_starts_with($reason, 'Pre-filter:')) {
                $buckets['Pre-filter']++;
            } elseif ($reason === 'AI returned WAIT') {
                $buckets['AI WAIT']++;
            } elseif (str_starts_with($reason, 'Signal validator:')) {
                $buckets['Signal validator']++;
            } elseif (str_contains($reason, 'Confidence') || str_contains($reason, 'Daily') || str_contains($reason, 'session') || str_contains($reason, 'open trades')) {
                $buckets['Risk rules']++;
            } else {
                $buckets['Other']++;
            }
        }

        return $buckets;
    }

    public function winRate(int $days = 30, ?int $accountId = null): ?float
    {
        $since = Carbon::now()->subDays($days);
        $trades = $this->scoped(Trade::query(), $accountId)
            ->where('status', 'CLOSED')->where('updated_at', '>=', $since)->get(['profit']);

        if ($trades->isEmpty()) {
            return null;
        }

        $wins = $trades->filter(fn ($t) => (float) $t->profit > 0)->count();

        return round($wins / $trades->count() * 100, 1);
    }

    /**
     * @return list<array{date: string, profit: float, cumulative: float}>
     */
    public function dailyPnl(int $days = 30, ?int $accountId = null): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $rows = $this->scoped(Trade::query(), $accountId)
            ->where('status', 'CLOSED')->where('updated_at', '>=', $since)
            ->select(DB::raw('DATE(updated_at) as day'), DB::raw('SUM(profit) as profit'))
            ->groupBy('day')->orderBy('day')->get();

        $cumulative = 0;
        $result = [];
        foreach ($rows as $row) {
            $profit = round((float) $row->profit, 2);
            $cumulative = round($cumulative + $profit, 2);
            $result[] = ['date' => (string) $row->day, 'profit' => $profit, 'cumulative' => $cumulative];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function symbolPerformance(int $days = 30, ?int $accountId = null): array
    {
        $since = Carbon::now()->subDays($days);
        $trades = $this->scoped(Trade::query(), $accountId)
            ->where('status', 'CLOSED')->where('updated_at', '>=', $since)
            ->get(['symbol', 'profit']);

        return $trades->groupBy('symbol')->map(function ($group, string $symbol) {
            $wins = $group->filter(fn ($t) => (float) $t->profit > 0)->count();

            return [
                'symbol' => $symbol,
                'trades' => $group->count(),
                'win_rate' => $group->count() > 0 ? round($wins / $group->count() * 100, 1) : 0,
                'net_profit' => round((float) $group->sum('profit'), 2),
            ];
        })->sortByDesc('net_profit')->values()->all();
    }

    private function scoped($query, ?int $accountId, string $column = 'account_id')
    {
        if ($accountId !== null) {
            $query->where($column, $accountId);
        }

        return $query;
    }
}
