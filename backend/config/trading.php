<?php

return [

  'api_token' => env('MT5_API_TOKEN'),

  'symbols' => array_filter(array_map('trim', explode(',', env('TRADING_SYMBOLS',
    'BTCUSDT,ETHUSDT,PAXGUSDT,XAUUSD,EURUSD,GBPUSD'
  )))),

  'timeframes' => ['M15', 'H1'],

  'candle_count' => (int) env('TRADING_CANDLE_COUNT', 50),

  'ai' => [
    'provider' => env('AI_PROVIDER', 'openai'),
    'openai' => [
      'api_key' => env('OPENAI_API_KEY'),
      'model' => env('OPENAI_MODEL', 'gpt-4o'),
      'model_suggestions' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini'],
    ],
    'anthropic' => [
      'api_key' => env('ANTHROPIC_API_KEY'),
      'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
      'model_suggestions' => ['claude-sonnet-4-6', 'claude-sonnet-4-5-20250929', 'claude-haiku-4-5-20251001'],
    ],
    'gemini' => [
      'api_key' => env('GEMINI_API_KEY'),
      'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
      'model_suggestions' => ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro'],
    ],
    'consensus' => [
      'enabled' => false,
      'providers' => ['openai', 'anthropic', 'gemini'],
      'min_agree' => 2,
    ],
  ],

  'risk' => [
    'max_risk_per_trade_pct' => (float) env('RISK_PER_TRADE_PCT', 1.0),
    'min_confidence' => (int) env('MIN_CONFIDENCE', 80),
    'max_open_trades' => (int) env('MAX_OPEN_TRADES', 3),
    'max_daily_drawdown_pct' => (float) env('MAX_DAILY_DRAWDOWN_PCT', 3.0),
    'max_daily_loss' => null,
    'max_daily_profit' => null,
    'trading_sessions' => env('TRADING_SESSIONS', '00:00-23:59'),
  ],

  'ai_entry' => [
    'strategy' => 'balanced',
    'min_risk_reward' => 2.0,
    'recent_candles' => 10,
  ],

  'pre_filter' => [
    'enabled' => true,
    'min_adx' => 15.0,
    'min_confluence_factors' => 2,
    'skip_neutral_setups' => true,
    'max_spread_multiplier' => 3.0,
    'max_spread_points' => 0,
  ],

  'signal_validator' => [
    'max_entry_slippage_points' => 50,
  ],

  'news' => [
    'enabled' => true,
    'calendar_url' => 'https://nfs.faireconomy.media/ff_calendar_thisweek.json',
    'block_minutes_before' => 30,
    'block_minutes_after' => 15,
    'lookahead_hours' => 8,
    'cache_minutes' => 60,
  ],

  'telegram' => [
    'enabled' => false,
    'bot_token' => null,
    'chat_id' => null,
    'daily_summary_time' => '20:00',
    'notify' => [
      'signals' => true,
      'rejections' => false,
      'trades' => true,
      'backtests' => true,
      'daily_summary' => true,
    ],
  ],

  'backtest' => [
    'max_bars_open' => 96,
  ],

];
