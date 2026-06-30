<?php

namespace App\Services\Backtesting;

use App\Models\BacktestRun;
use App\Models\MarketSnapshot;
use App\Services\AI\MarketContextEnricher;
use App\Services\PreTradeFilterService;
use App\Services\SignalValidatorService;
use Illuminate\Support\Collection;

class BacktestEngine
{
    public function __construct(
        private PreTradeFilterService $preFilter,
        private SignalValidatorService $signalValidator,
    ) {}

    public function run(BacktestRun $run): BacktestRun
    {
        $startedAt = microtime(true);
        $run->update(['status' => 'RUNNING']);

        try {
            $params = $run->params_json ?? [];
            $results = $this->simulate($this->loadSnapshots($run), $params);
            $run->update([
                'status' => 'COMPLETED',
                'results_json' => $results,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        }

        return $run->fresh();
    }

    /**
     * @return Collection<int, MarketSnapshot>
     */
    private function loadSnapshots(BacktestRun $run): Collection
    {
        $query = MarketSnapshot::query()
            ->where('symbol', $run->symbol)
            ->whereDate('created_at', '>=', $run->from_date)
            ->whereDate('created_at', '<=', $run->to_date)
            ->orderBy('created_at');

        if ($run->account_id) {
            $query->where('account_id', $run->account_id);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, MarketSnapshot>  $snapshots
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function simulate(Collection $snapshots, array $params): array
    {
        $strategy = (string) ($params['strategy'] ?? config('trading.ai_entry.strategy', 'balanced'));
        $minConfidence = (int) ($params['min_confidence'] ?? config('trading.risk.min_confidence', 80));

        $openPosition = null;
        $trades = [];
        $equityR = 0.0;
        $peakR = 0.0;
        $maxDrawdownR = 0.0;

        foreach ($snapshots as $snapshot) {
            $symbolData = $snapshot->snapshot_json ?? [];
            if (empty($symbolData['candles'])) {
                continue;
            }

            if ($openPosition !== null) {
                $openPosition['bars_open'] = ($openPosition['bars_open'] ?? 0) + 1;
                $exit = $this->checkExit($openPosition, $symbolData);
                if ($exit) {
                    $equityR += $exit['r_multiple'];
                    $peakR = max($peakR, $equityR);
                    $maxDrawdownR = max($maxDrawdownR, $peakR - $equityR);
                    $trades[] = array_merge($openPosition, $exit, ['closed_at' => $snapshot->created_at?->toDateTimeString()]);
                    $openPosition = null;
                }
                continue;
            }

            $enriched = MarketContextEnricher::enrich($symbolData);
            if ($this->preFilter->getSkipReason($symbolData, $enriched)) {
                continue;
            }

            $entry = $this->generateRuleEntry($enriched, $strategy, $minConfidence);
            if (! $entry) {
                continue;
            }

            $fakeSignal = new \App\Models\Signal([
                'action' => $entry['action'],
                'entry_price' => $entry['entry'],
                'stop_loss' => $entry['stop_loss'],
                'take_profit' => $entry['take_profit'],
            ]);

            if ($this->signalValidator->getRejectionReason($fakeSignal, $symbolData)) {
                continue;
            }

            $openPosition = array_merge($entry, ['opened_at' => $snapshot->created_at?->toDateTimeString()]);
        }

        $wins = collect($trades)->where('outcome', 'win')->count();
        $total = count($trades);

        return [
            'snapshots_processed' => $snapshots->count(),
            'total_trades' => $total,
            'wins' => $wins,
            'losses' => $total - $wins,
            'win_rate' => $total > 0 ? round($wins / $total * 100, 1) : 0,
            'total_r' => round($equityR, 2),
            'max_drawdown_r' => round($maxDrawdownR, 2),
            'strategy' => $strategy,
            'trades' => array_slice($trades, -100),
        ];
    }

    /**
     * @param  array<string, mixed>  $enriched
     * @return array<string, mixed>|null
     */
    private function generateRuleEntry(array $enriched, string $strategy, int $minConfidence): ?array
    {
        $analysis = $enriched['analysis'] ?? [];
        $bias = $analysis['confluence']['bias'] ?? 'neutral';
        $bullish = (int) ($analysis['confluence']['bullish_factors'] ?? 0);
        $bearish = (int) ($analysis['confluence']['bearish_factors'] ?? 0);
        $mtf = $analysis['multi_timeframe']['alignment'] ?? 'neutral';
        $levels = $analysis['suggested_atr_levels'] ?? null;

        if (! $levels) {
            return null;
        }

        $action = null;
        $confidence = 70;

        if ($strategy === 'active') {
            if ($bullish >= 2 && $mtf !== 'bearish') {
                $action = 'BUY';
                $confidence = min(95, 70 + $bullish * 3);
            } elseif ($bearish >= 2 && $mtf !== 'bullish') {
                $action = 'SELL';
                $confidence = min(95, 70 + $bearish * 3);
            }
        } elseif ($strategy === 'conservative') {
            if ($bias === 'bullish' && $bullish >= 3 && $mtf === 'bullish') {
                $action = 'BUY';
                $confidence = min(95, 75 + $bullish * 2);
            } elseif ($bias === 'bearish' && $bearish >= 3 && $mtf === 'bearish') {
                $action = 'SELL';
                $confidence = min(95, 75 + $bearish * 2);
            }
        } else {
            if ($bias === 'bullish' && $bullish >= 2 && in_array($mtf, ['bullish', 'neutral'], true)) {
                $action = 'BUY';
                $confidence = min(92, 72 + $bullish * 2);
            } elseif ($bias === 'bearish' && $bearish >= 2 && in_array($mtf, ['bearish', 'neutral'], true)) {
                $action = 'SELL';
                $confidence = min(92, 72 + $bearish * 2);
            }
        }

        if (! $action || $confidence < $minConfidence) {
            return null;
        }

        $side = strtolower($action);

        return [
            'action' => $action,
            'entry' => (float) $levels[$side]['entry'],
            'stop_loss' => (float) $levels[$side]['stop_loss'],
            'take_profit' => (float) $levels[$side]['take_profit'],
            'confidence' => $confidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, mixed>  $symbolData
     * @return array<string, mixed>|null
     */
    private function checkExit(array $position, array $symbolData): ?array
    {
        $candles = $symbolData['candles'] ?? [];
        if ($candles === []) {
            return null;
        }

        $last = $candles[array_key_last($candles)];
        $high = (float) ($last['high'] ?? 0);
        $low = (float) ($last['low'] ?? 0);
        $entry = (float) $position['entry'];
        $sl = (float) $position['stop_loss'];
        $tp = (float) $position['take_profit'];
        $risk = abs($entry - $sl);
        $reward = abs($tp - $entry);
        $rr = $risk > 0 ? $reward / $risk : 0;

        if ($position['action'] === 'BUY') {
            $slHit = $low <= $sl;
            $tpHit = $high >= $tp;
        } else {
            $slHit = $high >= $sl;
            $tpHit = $low <= $tp;
        }

        if ($slHit) {
            return ['outcome' => 'loss', 'r_multiple' => -1.0, 'exit_price' => $sl, 'rr' => round($rr, 2)];
        }
        if ($tpHit) {
            return ['outcome' => 'win', 'r_multiple' => round($rr, 2), 'exit_price' => $tp, 'rr' => round($rr, 2)];
        }

        $maxBars = (int) config('trading.backtest.max_bars_open', 96);
        if (($position['bars_open'] ?? 0) >= $maxBars) {
            $close = (float) ($symbolData['market']['bid'] ?? $symbolData['market']['ask'] ?? $entry);
            $rMultiple = $risk > 0
                ? ($position['action'] === 'BUY' ? ($close - $entry) / $risk : ($entry - $close) / $risk)
                : 0;

            return [
                'outcome' => $rMultiple >= 0 ? 'win' : 'loss',
                'r_multiple' => round($rMultiple, 2),
                'exit_price' => $close,
                'rr' => round($rr, 2),
                'timeout' => true,
            ];
        }

        return null;
    }
}
