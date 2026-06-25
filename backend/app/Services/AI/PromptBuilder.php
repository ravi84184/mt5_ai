<?php

namespace App\Services\AI;

class PromptBuilder
{
    public static function entrySystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional forex and crypto trading analyst. Analyze the provided market data and return a JSON object only.

Rules:
- action must be one of: BUY, SELL, WAIT
- confidence is an integer from 0 to 100
- entry_price, stop_loss, take_profit must be numeric prices aligned with the symbol
- If conditions are unclear or risky, return WAIT with confidence below 70
- reason must be a concise explanation (max 200 chars)

Response JSON schema:
{
  "symbol": "XAUUSD",
  "action": "BUY",
  "confidence": 88,
  "entry_price": 3365.5,
  "stop_loss": 3350,
  "take_profit": 3395,
  "reason": "Bullish breakout above EMA200"
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function entryUserPrompt(array $context): string
    {
        return 'Analyze this market data and provide a trade decision:\n'.json_encode($context, JSON_PRETTY_PRINT);
    }

    public static function positionSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional trade manager. Analyze the open position and market context. Return JSON only.

Rules:
- action must be one of: HOLD, CLOSE, MOVE_SL, MOVE_TO_BREAKEVEN, PARTIAL_CLOSE
- new_sl is required for MOVE_SL and MOVE_TO_BREAKEVEN
- close_volume (lot size) is required for PARTIAL_CLOSE
- reason must be concise (max 200 chars)

Response JSON schema:
{
  "action": "MOVE_SL",
  "new_sl": 3370,
  "reason": "Protect profits"
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function positionUserPrompt(array $context): string
    {
        return 'Manage this open position:\n'.json_encode($context, JSON_PRETTY_PRINT);
    }
}
