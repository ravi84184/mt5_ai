# AI Trading Platform - Technical Specification

## 1. Project Overview

Develop an AI-powered automated trading platform using MetaTrader 5 (MT5), PHP/Laravel, and AI models (GPT/Claude/Gemini).

The system will:

1. Receive market data from MT5.
2. Send market data to a PHP backend.
3. PHP sends the data to AI models.
4. AI predicts:
   - Entry
   - Stop Loss
   - Take Profit
   - Confidence Score
   - Trade Management Decision

5. MT5 receives the decision and executes trades.
6. MT5 continuously sends trade updates to PHP.
7. AI manages open positions.

---

# 2. Technology Stack

## Trading Terminal

- MetaTrader 5
- MQL5 Expert Advisor

## Backend

- PHP 8+
- Laravel 12+
- MySQL 8
- Redis (optional)
- Laravel Queues

## AI Providers

- OpenAI GPT
- Anthropic Claude
- Google Gemini

## Infrastructure

- Linux VPS
- Nginx
- Supervisor (queue workers)

---

# 3. High-Level Architecture

MT5 EA
↓
Laravel API
↓
Queue Job
↓
AI Service
↓
MySQL
↓
MT5 Polling
↓
Trade Execution

---

# 4. Trading Symbols

System must support multiple symbols.

Initial symbols:

- BTCUSDT
- ETHUSDT
- PAXGUSDT
- XAUUSD
- EURUSD
- GBPUSD

System should be configurable to support unlimited symbols.

---

# 5. MT5 Responsibilities

The MT5 Expert Advisor is responsible for:

## 5.1 Market Data Collection

Every new candle:

- Collect last N candles.
- Calculate indicators.
- Collect account information.
- Collect open position information.

Recommended timeframe:

- M15
- H1

The EA should NOT send data every tick.

---

## 5.2 Send Market Data

Endpoint:

POST /api/market-data

Payload:

{
"account": {
"login": 123456,
"balance": 10000,
"equity": 10250,
"free_margin": 9500
},

```
"symbols": [
    {
        "symbol": "XAUUSD",
        "timeframe": "M15",

        "indicators": {
            "ema20": 3350,
            "ema50": 3340,
            "ema200": 3300,
            "rsi": 64,
            "atr": 12
        },

        "candles": [
            {
                "time": "2026-06-24 10:00",
                "open": 3350,
                "high": 3360,
                "low": 3345,
                "close": 3358,
                "volume": 1000
            }
        ]
    }
]
```

}

Expected response:

{
"status": "accepted"
}

The request should return immediately.

AI processing should occur asynchronously using queues.

---

# 6. AI Processing

AI processing should be asynchronous.

Laravel Queue Job:

ProcessMarketAnalysisJob

Responsibilities:

- Receive market data.
- Prepare prompt.
- Send request to AI provider.
- Store result.

Supported AI Providers:

- GPT
- Claude
- Gemini

The provider should be configurable.

---

# 7. AI Entry Decision

AI should determine:

- BUY
- SELL
- WAIT

AI Response:

{
"symbol": "XAUUSD",
"action": "BUY",
"confidence": 88,
"entry_price": 3365.5,
"stop_loss": 3350,
"take_profit": 3395,
"reason": "Bullish breakout above EMA200"
}

Possible actions:

- BUY
- SELL
- WAIT

---

# 8. Signal Storage

Table: signals

Columns:

id
account_id
symbol
action
entry_price
stop_loss
take_profit
confidence
reason
status
ticket
created_at
updated_at

Status values:

- PENDING
- EXECUTED
- REJECTED
- CLOSED

---

# 9. MT5 Signal Polling

MT5 should poll every 5-10 seconds.

Endpoint:

GET /api/signals?account=123456

Response:

{
"id": 125,
"symbol": "XAUUSD",
"action": "BUY",
"confidence": 88,
"entry_price": 3365.5,
"stop_loss": 3350,
"take_profit": 3395
}

If no signal:

{
"status": "NO_SIGNAL"
}

---

# 10. Trade Execution

MT5 validates:

- Confidence threshold.
- No existing position.
- Daily risk limits.
- Trading session rules.

Then executes trade.

After successful execution:

POST /api/signals/executed

Payload:

{
"signal_id": 125,
"ticket": 987654321,
"status": "EXECUTED"
}

---

# 11. Open Position Management

When a position exists, MT5 should periodically send position data.

Recommended interval:

Every new candle.

Endpoint:

POST /api/position-analysis

Payload:

{
"ticket": 987654321,

```
"position": {
    "symbol": "XAUUSD",
    "type": "BUY",
    "entry_price": 3365,
    "current_price": 3375,
    "profit": 50,
    "sl": 3350,
    "tp": 3395,
    "duration_minutes": 120
},

"market_data": {
    "candles": [],
    "indicators": {}
}
```

}

---

# 12. AI Trade Management

AI should decide:

- HOLD
- CLOSE
- MOVE_SL
- MOVE_TO_BREAKEVEN
- PARTIAL_CLOSE

Example:

{
"action": "MOVE_SL",
"new_sl": 3370,
"reason": "Protect profits"
}

Example:

{
"action": "CLOSE",
"reason": "Momentum weakening"
}

---

# 13. MT5 Trade Updates

MT5 should use OnTradeTransaction().

Whenever:

- Position opens
- Position closes
- SL hit
- TP hit

MT5 sends update.

Endpoint:

POST /api/trades/update

Payload:

{
"ticket": 987654321,
"status": "CLOSED",
"profit": 125.50,
"close_price": 3390
}

---

# 14. Database Tables

accounts

id
mt5_login
broker
balance
equity

signals

id
account_id
symbol
action
entry_price
stop_loss
take_profit
confidence
reason
status
ticket

trades

id
ticket
signal_id
symbol
type
lot
entry_price
close_price
profit
status

trade_management_logs

id
ticket
action
old_sl
new_sl
reason

market_snapshots

id
symbol
timeframe
snapshot_json

---

# 15. Risk Management Rules

Configurable:

- Maximum risk per trade.
- Maximum daily loss.
- Maximum daily profit.
- Maximum open trades.
- Trading sessions.
- Maximum drawdown.
- Minimum confidence score.

Default:

Risk per trade: 1%

Minimum confidence: 80%

Maximum open trades: 3

Maximum daily drawdown: 3%

---

# 16. Future Enhancements

Phase 2:

- Multi-AI consensus.
- Backtesting engine.
- Trade analytics dashboard.
- AI learning from historical performance.
- Telegram notifications.
- Web dashboard.
- Portfolio-level risk management.

---

# 17. Development Milestones

Milestone 1

MT5 → Laravel communication.

Milestone 2

Laravel → AI integration.

Milestone 3

Signal generation.

Milestone 4

MT5 auto execution.

Milestone 5

Trade synchronization.

Milestone 6

AI position management.

Milestone 7

Analytics dashboard.

Milestone 8

Multi-AI consensus engine.
