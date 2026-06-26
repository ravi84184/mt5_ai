<?php

return [

  // MT5_API_TOKEN: optional legacy global token (per-account tokens preferred)

  'api_token' => env('MT5_API_TOKEN', 'change-me-in-production'),

  // Defaults — overridden by Super Admin → System → Trading settings when saved
  'symbols' => array_filter(array_map('trim', explode(',', env('TRADING_SYMBOLS',
    'BTCUSDT,ETHUSDT,PAXGUSDT,XAUUSD,EURUSD,GBPUSD'
  )))),

  'timeframes' => ['M15', 'H1'],

  'candle_count' => (int) env('TRADING_CANDLE_COUNT', 50),

  'ai' => [
    'provider' => env('AI_PROVIDER', 'openai'),
    'openai' => [
      'api_key' => env('OPENAI_API_KEY'),
      'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'anthropic' => [
      'api_key' => env('ANTHROPIC_API_KEY'),
      'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],
    'gemini' => [
      'api_key' => env('GEMINI_API_KEY'),
      'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
  ],

  'risk' => [
    'max_risk_per_trade_pct' => (float) env('RISK_PER_TRADE_PCT', 1.0),
    'min_confidence' => (int) env('MIN_CONFIDENCE', 80),
    'max_open_trades' => (int) env('MAX_OPEN_TRADES', 3),
    'max_daily_drawdown_pct' => (float) env('MAX_DAILY_DRAWDOWN_PCT', 3.0),
    'max_daily_loss' => env('MAX_DAILY_LOSS') ? (float) env('MAX_DAILY_LOSS') : null,
    'max_daily_profit' => env('MAX_DAILY_PROFIT') ? (float) env('MAX_DAILY_PROFIT') : null,
    'trading_sessions' => env('TRADING_SESSIONS', '00:00-23:59'),
  ],

];
