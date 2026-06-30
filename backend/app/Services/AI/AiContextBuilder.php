<?php

namespace App\Services\AI;

use App\Models\Account;

class AiContextBuilder
{
    /**
     * @param  array<string, mixed>  $symbolData
     * @param  array<string, mixed>  $enriched
     * @return array<string, mixed>
     */
    public static function buildEntryContext(
        Account $account,
        array $symbolData,
        array $enriched,
        string $symbol,
    ): array {
        $candleLimit = (int) config('trading.ai_entry.recent_candles', 10);
        $candles = $symbolData['candles'] ?? [];
        if ($candleLimit > 0 && count($candles) > $candleLimit) {
            $candles = array_slice($candles, -$candleLimit);
        }

        $news = app(EconomicCalendarService::class)->getContextForSymbol($symbol);

        return [
            'account' => [
                'balance' => $account->balance,
                'equity' => $account->equity,
            ],
            'symbol' => array_merge($symbolData, [
                'symbol' => $symbol,
                'recent_candles' => $candles,
            ]),
            'market' => $symbolData['market'] ?? [],
            'symbol_info' => $symbolData['symbol_info'] ?? [],
            'analysis' => $enriched['analysis'] ?? [],
            'news' => $news,
            'correlation' => $symbolData['correlation'] ?? [],
            'recent_performance' => TradingHistoryContext::forAccountAndSymbol($account, $symbol),
            'risk' => array_merge(config('trading.risk'), [
                'min_confidence' => $account->resolvedMinConfidence(),
                'max_open_trades' => $account->resolvedMaxOpenTrades(),
            ]),
            'ai_strategy' => config('trading.ai_entry.strategy', 'balanced'),
        ];
    }
}
