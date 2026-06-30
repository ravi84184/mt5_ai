<?php

namespace App\Services;

use App\Services\AI\EconomicCalendarService;

class PreTradeFilterService
{
    /**
     * @param  array<string, mixed>  $symbolData
     * @param  array<string, mixed>  $enriched
     */
    public function getSkipReason(array $symbolData, array $enriched): ?string
    {
        if (! config('trading.pre_filter.enabled', true)) {
            return null;
        }

        $analysis = $enriched['analysis'] ?? [];
        $symbol = (string) ($symbolData['symbol'] ?? '');

        if ($reason = $this->newsBlockReason($symbol)) {
            return $reason;
        }

        if ($reason = $this->spreadBlockReason($symbolData)) {
            return $reason;
        }

        if ($reason = $this->choppyMarketReason($analysis)) {
            return $reason;
        }

        if ($reason = $this->weakSetupReason($analysis)) {
            return $reason;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function choppyMarketReason(array $analysis): ?string
    {
        $adx = (float) ($analysis['adx']['value'] ?? 0);
        $volatility = $analysis['volatility']['regime'] ?? 'normal';
        $mtfAlignment = $analysis['multi_timeframe']['alignment'] ?? 'neutral';
        $minAdx = (float) config('trading.pre_filter.min_adx', 15);

        if ($adx < $minAdx && $volatility === 'compressed' && $mtfAlignment === 'mixed') {
            return "Choppy market (ADX {$adx}, compressed volatility, mixed MTF)";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function weakSetupReason(array $analysis): ?string
    {
        if (! config('trading.pre_filter.skip_neutral_setups', true)) {
            return null;
        }

        $adx = (float) ($analysis['adx']['value'] ?? 0);
        $bias = $analysis['confluence']['bias'] ?? 'neutral';
        $bullish = (int) ($analysis['confluence']['bullish_factors'] ?? 0);
        $bearish = (int) ($analysis['confluence']['bearish_factors'] ?? 0);
        $mtfAlignment = $analysis['multi_timeframe']['alignment'] ?? 'neutral';
        $minAdx = (float) config('trading.pre_filter.min_adx', 15);
        $minFactors = (int) config('trading.pre_filter.min_confluence_factors', 2);

        if ($bias === 'neutral' && $adx < $minAdx && max($bullish, $bearish) < $minFactors) {
            return "No clear edge (neutral bias, ADX {$adx}, insufficient confluence)";
        }

        if ($mtfAlignment === 'mixed' && $bias === 'neutral') {
            return 'Mixed MTF with neutral confluence';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $symbolData
     */
    private function spreadBlockReason(array $symbolData): ?string
    {
        $spreadPoints = (float) ($symbolData['market']['spread'] ?? 0);
        $typicalSpreadPoints = (float) ($symbolData['symbol_info']['typical_spread_points'] ?? 0);

        if ($spreadPoints <= 0) {
            return null;
        }

        $maxMultiplier = (float) config('trading.pre_filter.max_spread_multiplier', 3.0);

        if ($typicalSpreadPoints > 0 && $spreadPoints > ($typicalSpreadPoints * $maxMultiplier)) {
            return sprintf('Spread too wide (%.0f pts vs typical %.0f pts)', $spreadPoints, $typicalSpreadPoints);
        }

        $maxPoints = (int) config('trading.pre_filter.max_spread_points', 0);
        if ($maxPoints > 0 && $spreadPoints > $maxPoints) {
            return sprintf('Spread exceeds %d points', $maxPoints);
        }

        return null;
    }

    private function newsBlockReason(string $symbol): ?string
    {
        if (! config('trading.news.enabled', true)) {
            return null;
        }

        return app(EconomicCalendarService::class)->getBlockReasonForSymbol($symbol);
    }
}
