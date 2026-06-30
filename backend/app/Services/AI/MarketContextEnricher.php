<?php

namespace App\Services\AI;

class MarketContextEnricher
{
    /**
     * @param  array<string, mixed>  $symbolData
     * @return array<string, mixed>
     */
    public static function enrich(array $symbolData): array
    {
        $candles = is_array($symbolData['candles'] ?? null) ? $symbolData['candles'] : [];
        $indicators = is_array($symbolData['indicators'] ?? null) ? $symbolData['indicators'] : [];

        $ema20 = (float) ($indicators['ema20'] ?? 0);
        $ema50 = (float) ($indicators['ema50'] ?? 0);
        $ema200 = (float) ($indicators['ema200'] ?? 0);
        $rsi = (float) ($indicators['rsi'] ?? 50);
        $atr = (float) ($indicators['atr'] ?? 0);
        $atrAvg = (float) ($indicators['atr_avg_20'] ?? $atr);
        $adx = (float) ($indicators['adx'] ?? 0);
        $macdHist = (float) ($indicators['macd_histogram'] ?? 0);

        $bid = (float) ($symbolData['market']['bid'] ?? 0);
        $ask = (float) ($symbolData['market']['ask'] ?? 0);
        $close = self::resolveClose($candles, $bid, $ask);

        $latest = self::latestCandle($candles);
        $open = (float) ($latest['open'] ?? $close);
        $high = (float) ($latest['high'] ?? $close);
        $low = (float) ($latest['low'] ?? $close);

        $emaStack = self::emaStack($ema20, $ema50, $ema200);
        $trend = self::trend($close, $ema20, $ema50, $ema200);
        $momentum = $candles !== [] ? self::recentMomentum($candles, 5) : self::emptyMomentum();
        $swing = $candles !== [] ? self::swingLevels($candles, 20, $symbolData) : [
            'recent_high' => null,
            'recent_low' => null,
        ];
        $candleType = $latest !== null ? self::candleType($open, $high, $low, $close) : null;

        $mtf = self::analyzeMultiTimeframe($symbolData['multi_timeframe'] ?? [], $close);
        $levels = self::analyzeLevels($symbolData['levels'] ?? [], $close, $atr, $symbolData);
        $volatility = self::analyzeVolatility($atr, $atrAvg, $indicators);
        $session = self::analyzeSession($symbolData['session'] ?? []);

        $bullishFactors = self::countBullishFactors(
            $close,
            $ema20,
            $ema50,
            $ema200,
            $rsi,
            $momentum,
            $emaStack,
            $macdHist,
            $adx,
        );
        $bearishFactors = self::countBearishFactors(
            $close,
            $ema20,
            $ema50,
            $ema200,
            $rsi,
            $momentum,
            $emaStack,
            $macdHist,
            $adx,
        );

        if ($mtf['alignment'] === 'bullish') {
            $bullishFactors += 2;
        } elseif ($mtf['alignment'] === 'bearish') {
            $bearishFactors += 2;
        }

        if (($volatility['regime'] ?? '') === 'compressed' && $adx < 20) {
            $bullishFactors = max(0, $bullishFactors - 1);
            $bearishFactors = max(0, $bearishFactors - 1);
        }

        $entryBid = $bid > 0 ? $bid : $close;
        $entryAsk = $ask > 0 ? $ask : $close;
        $suggestedSlTp = $atr > 0 && $close > 0 ? [
            'buy' => [
                'entry' => self::roundPrice($entryAsk, $symbolData),
                'stop_loss' => self::roundPrice($entryAsk - ($atr * 1.5), $symbolData),
                'take_profit' => self::roundPrice($entryAsk + ($atr * 3), $symbolData),
            ],
            'sell' => [
                'entry' => self::roundPrice($entryBid, $symbolData),
                'stop_loss' => self::roundPrice($entryBid + ($atr * 1.5), $symbolData),
                'take_profit' => self::roundPrice($entryBid - ($atr * 3), $symbolData),
            ],
        ] : null;

        $analysis = [
            'trend' => $trend,
            'ema_stack' => $emaStack,
            'price_vs_emas' => [
                'above_ema20' => $ema20 > 0 ? $close > $ema20 : null,
                'above_ema50' => $ema50 > 0 ? $close > $ema50 : null,
                'above_ema200' => $ema200 > 0 ? $close > $ema200 : null,
            ],
            'rsi' => [
                'value' => $rsi,
                'zone' => self::rsiZone($rsi),
            ],
            'macd' => [
                'histogram' => $macdHist,
                'bias' => $macdHist > 0 ? 'bullish' : ($macdHist < 0 ? 'bearish' : 'neutral'),
            ],
            'adx' => [
                'value' => $adx,
                'trend_strength' => $adx >= 25 ? 'strong' : ($adx >= 20 ? 'moderate' : 'weak'),
            ],
            'volatility' => $volatility,
            'session' => $session,
            'multi_timeframe' => $mtf,
            'key_levels' => $levels,
            'momentum_last_5' => $momentum,
            'swing_levels' => $swing,
            'confluence' => [
                'bullish_factors' => $bullishFactors,
                'bearish_factors' => $bearishFactors,
                'bias' => $bullishFactors > $bearishFactors
                    ? 'bullish'
                    : ($bearishFactors > $bullishFactors ? 'bearish' : 'neutral'),
            ],
            'suggested_atr_levels' => $suggestedSlTp,
        ];

        if ($latest !== null) {
            $analysis['latest_candle'] = [
                'time' => $latest['time'] ?? null,
                'open' => self::roundPrice($open, $symbolData),
                'high' => self::roundPrice($high, $symbolData),
                'low' => self::roundPrice($low, $symbolData),
                'close' => self::roundPrice($close, $symbolData),
                'type' => $candleType,
            ];
        } else {
            $analysis['latest_candle'] = null;
            $analysis['data_gaps'] = ['candles'];
        }

        if ($bid > 0 || $ask > 0) {
            $analysis['price'] = [
                'bid' => $bid > 0 ? self::roundPrice($bid, $symbolData) : null,
                'ask' => $ask > 0 ? self::roundPrice($ask, $symbolData) : null,
                'spread' => $symbolData['market']['spread'] ?? null,
            ];
        }

        return ['analysis' => $analysis];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     */
    private static function resolveClose(array $candles, float $bid, float $ask): float
    {
        $latest = self::latestCandle($candles);
        if ($latest !== null) {
            return (float) ($latest['close'] ?? 0);
        }

        if ($bid > 0 && $ask > 0) {
            return ($bid + $ask) / 2;
        }

        return $bid > 0 ? $bid : $ask;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     * @return array<string, mixed>|null
     */
    private static function latestCandle(array $candles): ?array
    {
        if ($candles === []) {
            return null;
        }

        $last = end($candles);

        return is_array($last) ? $last : null;
    }

    /**
     * @param  array<string, mixed>  $mtfData
     * @return array<string, mixed>
     */
    private static function analyzeMultiTimeframe(array $mtfData, float $close): array
    {
        $result = [
            'timeframes' => [],
            'alignment' => 'neutral',
            'summary' => 'No higher-timeframe data',
        ];

        if ($close <= 0 || $mtfData === []) {
            return $result;
        }

        $bullish = 0;
        $bearish = 0;
        $neutral = 0;

        foreach (['H1', 'H4'] as $tf) {
            $data = $mtfData[$tf] ?? null;
            if (! is_array($data)) {
                continue;
            }

            $ind = is_array($data['indicators'] ?? null) ? $data['indicators'] : $data;
            $e20 = (float) ($ind['ema20'] ?? 0);
            $e50 = (float) ($ind['ema50'] ?? 0);
            $e200 = (float) ($ind['ema200'] ?? 0);
            $rsi = (float) ($ind['rsi'] ?? 50);
            $tfTrend = self::trend($close, $e20, $e50, $e200);
            $tfStack = self::emaStack($e20, $e50, $e200);

            $result['timeframes'][$tf] = [
                'trend' => $tfTrend,
                'ema_stack' => $tfStack,
                'rsi' => $rsi,
            ];

            match ($tfTrend) {
                'bullish' => $bullish++,
                'bearish' => $bearish++,
                default => $neutral++,
            };
        }

        $tracked = $bullish + $bearish + $neutral;
        if ($tracked === 0) {
            return $result;
        }

        if ($bullish >= 2 && $bearish === 0) {
            $result['alignment'] = 'bullish';
            $result['summary'] = 'H1/H4 aligned bullish';
        } elseif ($bearish >= 2 && $bullish === 0) {
            $result['alignment'] = 'bearish';
            $result['summary'] = 'H1/H4 aligned bearish';
        } elseif ($bullish > 0 && $bearish > 0) {
            $result['alignment'] = 'mixed';
            $result['summary'] = 'H1/H4 conflict — mixed bias';
        } elseif ($bullish === 0 && $bearish === 0) {
            $result['alignment'] = 'neutral';
            $result['summary'] = 'H1/H4 neutral or ranging';
        } else {
            $result['alignment'] = 'mixed';
            $result['summary'] = 'Partial higher-timeframe alignment';
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $levels
     * @return array<string, mixed>
     */
    private static function analyzeLevels(array $levels, float $close, float $atr, array $symbolData): array
    {
        $pdh = (float) ($levels['prev_day_high'] ?? 0);
        $pdl = (float) ($levels['prev_day_low'] ?? 0);
        $pdc = (float) ($levels['prev_day_close'] ?? 0);
        $weekHigh = isset($levels['week_high']) ? (float) $levels['week_high'] : null;
        $weekLow = isset($levels['week_low']) ? (float) $levels['week_low'] : null;

        $distResistance = ($pdh > 0 && $close > 0) ? $pdh - $close : null;
        $distSupport = ($pdl > 0 && $close > 0) ? $close - $pdl : null;

        return [
            'prev_day_high' => $pdh > 0 ? self::roundPrice($pdh, $symbolData) : null,
            'prev_day_low' => $pdl > 0 ? self::roundPrice($pdl, $symbolData) : null,
            'prev_day_close' => $pdc > 0 ? self::roundPrice($pdc, $symbolData) : null,
            'week_high' => $weekHigh !== null && $weekHigh > 0 ? self::roundPrice($weekHigh, $symbolData) : null,
            'week_low' => $weekLow !== null && $weekLow > 0 ? self::roundPrice($weekLow, $symbolData) : null,
            'price_vs_pdh' => $pdh > 0 ? ($close > $pdh ? 'above' : 'below') : null,
            'price_vs_pdl' => $pdl > 0 ? ($close > $pdl ? 'above' : 'below') : null,
            'distance_to_resistance_atr' => ($distResistance !== null && $atr > 0)
                ? round($distResistance / $atr, 2)
                : null,
            'distance_to_support_atr' => ($distSupport !== null && $atr > 0)
                ? round($distSupport / $atr, 2)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $indicators
     * @return array<string, mixed>
     */
    private static function analyzeVolatility(float $atr, float $atrAvg, array $indicators): array
    {
        $ratio = $atrAvg > 0 ? round($atr / $atrAvg, 2) : 1.0;
        $bbWidth = null;

        if (isset($indicators['bb_upper'], $indicators['bb_lower'], $indicators['bb_middle'])) {
            $mid = (float) $indicators['bb_middle'];
            if ($mid > 0) {
                $bbWidth = round(
                    ((float) $indicators['bb_upper'] - (float) $indicators['bb_lower']) / $mid * 100,
                    2,
                );
            }
        }

        $regime = match (true) {
            $ratio >= 1.3 => 'expanding',
            $ratio <= 0.7 => 'compressed',
            default => 'normal',
        };

        return [
            'atr' => $atr,
            'atr_avg_20' => $atrAvg,
            'atr_ratio' => $ratio,
            'bb_width_pct' => $bbWidth,
            'regime' => $regime,
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private static function analyzeSession(array $session): array
    {
        $hour = (int) ($session['utc_hour'] ?? -1);
        $name = is_string($session['session'] ?? null) && $session['session'] !== ''
            ? $session['session']
            : self::sessionName($hour);

        return [
            'utc_hour' => $hour >= 0 ? $hour : null,
            'day_of_week' => $session['day_of_week'] ?? null,
            'session' => $name,
            'liquidity' => in_array($name, ['london', 'new_york', 'london_ny_overlap'], true)
                ? 'high'
                : 'moderate',
        ];
    }

    private static function sessionName(int $hour): string
    {
        if ($hour >= 0 && $hour < 7) {
            return 'asia';
        }
        if ($hour >= 7 && $hour < 12) {
            return 'london';
        }
        if ($hour >= 12 && $hour < 17) {
            return 'london_ny_overlap';
        }
        if ($hour >= 17 && $hour < 22) {
            return 'new_york';
        }

        return 'asia_late';
    }

    /**
     * @param  array<string, mixed>  $symbolData
     */
    private static function digits(array $symbolData): int
    {
        $info = $symbolData['symbol_info'] ?? [];

        if (isset($info['digits'])) {
            return (int) $info['digits'];
        }

        return (int) ($symbolData['market']['digits'] ?? 5);
    }

    /**
     * @param  array<string, mixed>  $symbolData
     */
    private static function roundPrice(float $value, array $symbolData): float
    {
        return round($value, self::digits($symbolData));
    }

    private static function emaStack(float $e20, float $e50, float $e200): string
    {
        if ($e20 > $e50 && $e50 > $e200) {
            return 'bullish_stack';
        }
        if ($e20 < $e50 && $e50 < $e200) {
            return 'bearish_stack';
        }

        return 'mixed';
    }

    private static function trend(float $close, float $e20, float $e50, float $e200): string
    {
        if ($close <= 0 || ($e20 <= 0 && $e50 <= 0 && $e200 <= 0)) {
            return 'neutral';
        }

        $above = (int) ($close > $e20) + (int) ($close > $e50) + (int) ($close > $e200);

        if ($above >= 2 && $e20 > $e50) {
            return 'bullish';
        }
        if ($above <= 1 && $e20 < $e50) {
            return 'bearish';
        }

        return 'neutral';
    }

    private static function rsiZone(float $rsi): string
    {
        if ($rsi >= 70) {
            return 'overbought';
        }
        if ($rsi <= 30) {
            return 'oversold';
        }
        if ($rsi >= 55) {
            return 'bullish';
        }
        if ($rsi <= 45) {
            return 'bearish';
        }

        return 'neutral';
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyMomentum(): array
    {
        return [
            'bullish_candles' => 0,
            'bearish_candles' => 0,
            'net_body' => 0.0,
            'direction' => 'flat',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     * @return array<string, mixed>
     */
    private static function recentMomentum(array $candles, int $count): array
    {
        $slice = array_slice($candles, -$count);
        $bullish = 0;
        $bearish = 0;
        $bodySum = 0.0;

        foreach ($slice as $candle) {
            if (! is_array($candle)) {
                continue;
            }

            $open = (float) ($candle['open'] ?? 0);
            $close = (float) ($candle['close'] ?? 0);

            if ($close > $open) {
                $bullish++;
            } elseif ($close < $open) {
                $bearish++;
            }

            $bodySum += ($close - $open);
        }

        return [
            'bullish_candles' => $bullish,
            'bearish_candles' => $bearish,
            'net_body' => round($bodySum, 5),
            'direction' => $bodySum > 0 ? 'up' : ($bodySum < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     * @param  array<string, mixed>  $symbolData
     * @return array<string, float|null>
     */
    private static function swingLevels(array $candles, int $lookback, array $symbolData): array
    {
        $slice = array_slice($candles, -$lookback);
        $highs = [];
        $lows = [];

        foreach ($slice as $candle) {
            if (! is_array($candle)) {
                continue;
            }

            $highs[] = (float) ($candle['high'] ?? 0);
            $lows[] = (float) ($candle['low'] ?? 0);
        }

        if ($highs === [] || $lows === []) {
            return [
                'recent_high' => null,
                'recent_low' => null,
            ];
        }

        return [
            'recent_high' => self::roundPrice(max($highs), $symbolData),
            'recent_low' => self::roundPrice(min($lows), $symbolData),
        ];
    }

    private static function candleType(float $open, float $high, float $low, float $close): string
    {
        $body = abs($close - $open);
        $range = max($high - $low, 0.00001);
        $upperWick = $high - max($open, $close);
        $lowerWick = min($open, $close) - $low;

        if ($body / $range < 0.1) {
            return 'doji';
        }
        if ($lowerWick > $body * 2 && $upperWick < $body) {
            return $close > $open ? 'hammer' : 'hanging_man';
        }
        if ($upperWick > $body * 2 && $lowerWick < $body) {
            return 'shooting_star';
        }

        return $close > $open ? 'bullish' : 'bearish';
    }

    /**
     * @param  array<string, mixed>  $momentum
     */
    private static function countBullishFactors(
        float $close,
        float $e20,
        float $e50,
        float $e200,
        float $rsi,
        array $momentum,
        string $stack,
        float $macdHist,
        float $adx,
    ): int {
        $factors = 0;

        if ($stack === 'bullish_stack') {
            $factors++;
        }
        if ($close > $e20 && $close > $e50) {
            $factors++;
        }
        if ($rsi >= 45 && $rsi <= 65) {
            $factors++;
        }
        if ($rsi <= 35) {
            $factors++;
        }
        if (($momentum['direction'] ?? '') === 'up') {
            $factors++;
        }
        if ($close > $e200) {
            $factors++;
        }
        if ($macdHist > 0) {
            $factors++;
        }
        if ($adx >= 20) {
            $factors++;
        }

        return $factors;
    }

    /**
     * @param  array<string, mixed>  $momentum
     */
    private static function countBearishFactors(
        float $close,
        float $e20,
        float $e50,
        float $e200,
        float $rsi,
        array $momentum,
        string $stack,
        float $macdHist,
        float $adx,
    ): int {
        $factors = 0;

        if ($stack === 'bearish_stack') {
            $factors++;
        }
        if ($close < $e20 && $close < $e50) {
            $factors++;
        }
        if ($rsi >= 35 && $rsi <= 55) {
            $factors++;
        }
        if ($rsi >= 65) {
            $factors++;
        }
        if (($momentum['direction'] ?? '') === 'down') {
            $factors++;
        }
        if ($close < $e200) {
            $factors++;
        }
        if ($macdHist < 0) {
            $factors++;
        }
        if ($adx >= 20) {
            $factors++;
        }

        return $factors;
    }
}
