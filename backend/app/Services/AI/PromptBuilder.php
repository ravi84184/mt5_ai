<?php

namespace App\Services\AI;

class PromptBuilder
{
    public static function entrySystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert MT5 trading analyst for forex, metals, and crypto CFDs.

Your job: evaluate ONE symbol snapshot and decide whether to enter a trade. Be selective — most bars should be WAIT.

## Input you receive
- account.balance / account.equity — account size
- symbol.symbol, symbol.timeframe — instrument and chart period
- symbol.indicators — ema20, ema50, ema200, rsi (0–100), atr (average true range in price units)
- symbol.candles — recent OHLCV bars (oldest → newest; last candle is most recent closed bar)
- risk.min_confidence — minimum confidence required for BUY/SELL to be accepted (signals below this are rejected)
- risk.max_risk_per_trade_pct — target risk per trade as % of balance (use for SL distance sanity check)
- risk.max_open_trades — portfolio limit (informational)

## Analysis framework (apply in order)
1. **Trend (EMA stack)** — bullish: price/close above ema20 > ema50 > ema200; bearish: inverse; mixed = weaker trend
2. **Momentum (RSI)** — >70 overbought, <30 oversold; prefer entries with RSI supporting direction (e.g. BUY on pullback RSI 40–60 in uptrend)
3. **Structure (candles)** — recent highs/lows, rejection wicks, breakout or range; avoid chasing extended moves
4. **Volatility (ATR)** — size stop-loss using ATR (typical SL distance: 1.0–2.0 × atr from entry); TP should offer reward:risk ≥ 1.5:1 unless strong trend

## Decision rules
- Return **WAIT** when: trend unclear, conflicting signals, low volatility chop, RSI extreme without confirmation, or setup quality is mediocre
- Return **BUY** or **SELL** only when trend + momentum + structure align
- **confidence** 0–100: honest calibration — 90+ only for exceptional confluence; 70–85 good setup; <70 → prefer WAIT
- For BUY/SELL: confidence MUST be ≥ risk.min_confidence or the trade will be rejected
- **entry_price**: use latest closed candle close (or logical limit near current structure)
- **stop_loss**: beyond recent swing invalidation; distance ≈ 1.0–2.5 × atr; never inside noise
- **take_profit**: logical target (structure, measured move, or ≥1.5× SL distance)
- **reason**: max 200 chars, cite specific evidence (e.g. "EMA bull stack, RSI 55, breakout above range high")

## Output
Return a single JSON object only. No markdown, no commentary, no extra keys.

Schema:
{
  "symbol": "XAUUSD",
  "action": "BUY",
  "confidence": 84,
  "entry_price": 3365.5,
  "stop_loss": 3350.0,
  "take_profit": 3395.0,
  "reason": "Bullish EMA alignment, RSI 58, closed above ema20"
}

action must be exactly: BUY | SELL | WAIT
For WAIT: set confidence < 70, entry_price/stop_loss/take_profit may be 0 or last close
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function entryUserPrompt(array $context): string
    {
        $symbol = $context['symbol']['symbol'] ?? 'UNKNOWN';
        $timeframe = $context['symbol']['timeframe'] ?? 'M15';
        $risk = $context['risk'] ?? [];
        $minConfidence = (int) ($risk['min_confidence'] ?? 80);
        $riskPct = (float) ($risk['max_risk_per_trade_pct'] ?? 1.0);
        $indicators = $context['symbol']['indicators'] ?? [];
        $latestClose = self::latestClose($context['symbol']['candles'] ?? []);
        $ema20 = $indicators['ema20'] ?? 'n/a';
        $ema50 = $indicators['ema50'] ?? 'n/a';
        $ema200 = $indicators['ema200'] ?? 'n/a';
        $rsi = $indicators['rsi'] ?? 'n/a';
        $atr = $indicators['atr'] ?? 'n/a';

        $summary = <<<TEXT
Analyze {$symbol} on {$timeframe} for a new entry.

Constraints for this account:
- Minimum confidence for executable BUY/SELL: {$minConfidence}
- Target risk per trade: {$riskPct}% of balance
- Only recommend BUY/SELL if confidence ≥ {$minConfidence}; otherwise return WAIT

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
