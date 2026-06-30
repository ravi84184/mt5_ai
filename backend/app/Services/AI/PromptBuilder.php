<?php

namespace App\Services\AI;

class PromptBuilder
{
    public static function entrySystemPrompt(): string
    {
        $minRr = config('trading.ai_entry.min_risk_reward', 2.0);
        $strategy = config('trading.ai_entry.strategy', 'balanced');

        return <<<PROMPT
You are an expert MT5 trading analyst for forex, metals, and crypto CFDs.

Evaluate the JSON payload and return ONE trade decision. Be selective — most bars should be WAIT.

When `analysis` is present, use it first (pre-computed MTF alignment, confluence, key levels, volatility, session).
Strategy mode: {$strategy}

Rules:
- If news.in_blackout is true → action MUST be WAIT
- Follow recent_performance.guidance — avoid revenge trading
- For gold (XAUUSD/PAXG): inverse DXY trend in correlation.dxy_trend can support direction
- Do NOT BUY if analysis.multi_timeframe.alignment is bearish; do NOT SELL if bullish
- stop_loss / take_profit must satisfy minimum R:R 1:{$minRr}
- Use analysis.suggested_atr_levels as baseline when unsure
- confidence must be ≥ risk.min_confidence for BUY/SELL

Return JSON only:
{"symbol":"XAUUSD","action":"BUY","confidence":84,"entry_price":3365.5,"stop_loss":3350,"take_profit":3395,"reason":"..."}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function entryUserPrompt(array $context): string
    {
        $symbolBlock = $context['symbol'] ?? [];
        $symbol = is_array($symbolBlock) ? ($symbolBlock['symbol'] ?? 'UNKNOWN') : (string) $symbolBlock;
        $timeframe = is_array($symbolBlock) ? ($symbolBlock['timeframe'] ?? 'M15') : 'M15';
        $risk = $context['risk'] ?? [];
        $minConfidence = (int) ($risk['min_confidence'] ?? 80);
        $indicators = is_array($symbolBlock) ? ($symbolBlock['indicators'] ?? []) : [];
        $candles = is_array($symbolBlock) ? ($symbolBlock['candles'] ?? $symbolBlock['recent_candles'] ?? []) : [];
        $latestClose = self::latestClose($candles);
        $riskPct = (float) ($risk['max_risk_per_trade_pct'] ?? 1.0);
        $hasAnalysis = ! empty($context['analysis']);
        $ema20 = $indicators['ema20'] ?? 'n/a';
        $ema50 = $indicators['ema50'] ?? 'n/a';
        $analysisNote = $hasAnalysis ? 'Pre-computed analysis block included — prefer it over raw candles.' : '';
        $ema200 = $indicators['ema200'] ?? 'n/a';
        $rsi = $indicators['rsi'] ?? 'n/a';
        $atr = $indicators['atr'] ?? 'n/a';

        $summary = <<<TEXT
Analyze {$symbol} on {$timeframe} for a new entry.

Constraints for this account:
- Minimum confidence for executable BUY/SELL: {$minConfidence}
- Target risk per trade: {$riskPct}% of balance
{$analysisNote}

Current snapshot:
- Latest closed price: {$latestClose}
- EMA20/50/200: {$ema20} / {$ema50} / {$ema200}
- RSI: {$rsi}
- ATR: {$atr}

Full market data (JSON):
TEXT;

        return $summary."\n".json_encode($context, JSON_PRETTY_PRINT);
    }

    public static function positionSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert MT5 position manager. Manage ONE open trade — protect capital first, then maximize risk-adjusted profit.

## Input you receive
- ticket — position ID
- position — symbol, type (BUY/SELL), entry_price, current_price, profit, sl, tp, duration_minutes
- market_data — optional candles/indicators (may be empty)

## Management framework
1. **If profit is negative** — avoid premature breakeven; only MOVE_SL if structure clearly improved; CLOSE if thesis invalidated
2. **If profit is positive** — consider MOVE_SL to lock gains, MOVE_TO_BREAKEVEN after meaningful move (e.g. >1× risk in profit), or HOLD if trend intact
3. **CLOSE** — reversal signals, time stop exceeded with stagnation, or risk exceeds reward
4. **PARTIAL_CLOSE** — take profit at key level while leaving runner; requires close_volume (lot size, must be < full position)
5. **HOLD** — default when no clear action; trend still valid and SL/TP appropriate

## Action rules
- action: HOLD | CLOSE | MOVE_SL | MOVE_TO_BREAKEVEN | PARTIAL_CLOSE
- new_sl: required for MOVE_SL and MOVE_TO_BREAKEVEN
  - BUY: new_sl must be < current_price and ≥ entry_price for breakeven
  - SELL: new_sl must be > current_price and ≤ entry_price for breakeven
  - Never widen stop (never increase risk)
- close_volume: required for PARTIAL_CLOSE (positive lot size)
- reason: max 200 chars, specific

## Output
Return a single JSON object only. No markdown, no extra keys.

Schema:
{
  "action": "MOVE_SL",
  "new_sl": 3370.0,
  "close_volume": null,
  "reason": "Trail stop below last swing low, +1.2R profit"
}

For HOLD or CLOSE: new_sl and close_volume should be null
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function positionUserPrompt(array $context): string
    {
        $position = $context['position'] ?? [];
        $ticket = $context['ticket'] ?? 'n/a';
        $symbol = $position['symbol'] ?? 'UNKNOWN';
        $type = strtoupper((string) ($position['type'] ?? 'UNKNOWN'));
        $entry = $position['entry_price'] ?? 'n/a';
        $current = $position['current_price'] ?? 'n/a';
        $profit = $position['profit'] ?? 0;
        $sl = $position['sl'] ?? 'none';
        $tp = $position['tp'] ?? 'none';
        $duration = (int) ($position['duration_minutes'] ?? 0);

        $summary = <<<TEXT
Manage open position #{$ticket}.

Position summary:
- Symbol: {$symbol} | Side: {$type}
- Entry: {$entry} | Current: {$current} | P&L: {$profit}
- SL: {$sl} | TP: {$tp}
- Duration: {$duration} minutes

Decide: HOLD, CLOSE, MOVE_SL, MOVE_TO_BREAKEVEN, or PARTIAL_CLOSE.

Full context (JSON):
TEXT;

        return $summary."\n".json_encode($context, JSON_PRETTY_PRINT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     */
    private static function latestClose(array $candles): string
    {
        if ($candles === []) {
            return 'n/a';
        }

        $last = end($candles);
        if (! is_array($last)) {
            return 'n/a';
        }

        return (string) ($last['close'] ?? 'n/a');
    }
}
