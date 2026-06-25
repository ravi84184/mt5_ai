# MT5 AI Trading Platform

AI-powered automated trading platform connecting MetaTrader 5, Laravel, and GPT/Claude/Gemini.

## Architecture

```
MT5 EA → Laravel API → Queue → AI Service → MySQL → MT5 Polling → Trade Execution
```

## Project Structure

```
mt5_ai/
├── backend/          # Laravel 13 API
├── mt5/              # MetaTrader 5 Expert Advisor
└── doc.md            # Full technical specification
```

## Quick Start

### 1. Laravel Backend

```bash
cd backend
cp .env.example .env
php artisan key:generate

# Configure database in .env (SQLite works for local dev)
# Set AI_PROVIDER and API keys (OPENAI_API_KEY, etc.)
# Set MT5_API_TOKEN

php artisan migrate
php artisan queue:work
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api`

### 2. MetaTrader 5 EA

1. Copy `mt5/AI_Trading_EA.mq5` to `MQL5/Experts/`
2. Compile in MetaEditor
3. In MT5: **Tools → Options → Expert Advisors → Allow WebRequest**
4. Add your API URL (e.g. `http://127.0.0.1:8000`)
5. Attach EA to a chart and configure inputs:
   - `InpApiBaseUrl` — Laravel API URL
   - `InpApiToken` — must match `MT5_API_TOKEN` in `.env`
   - `InpSymbols` — comma-separated symbols
   - `InpMinConfidence` — default 80
6. **Manual AI request:** click **Ask AI Entry** on chart, or run script `AI_Manual_Ask.mq5`

### 3. Production

- **AWS (EC2, MySQL on server):** see [DEPLOYMENT_AWS.md](DEPLOYMENT_AWS.md)
- **Hostinger shared hosting:** see [DEPLOYMENT_HOSTINGER.md](DEPLOYMENT_HOSTINGER.md)
- **No signals?** see [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

### 4. Dashboard

Web UI at `/dashboard` (password-protected). **No npm required** — uses plain PHP Blade + CSS.

```env
DASHBOARD_PASSWORD=your-secure-password
```

After deploying code to the server:

```bash
cd /var/www/mt5_ai/backend
git pull
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Open `https://your-domain.com/dashboard` and sign in.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/market-data` | Receive candles + indicators (async AI) |
| GET | `/api/signals?account={login}` | Poll pending trade signal |
| POST | `/api/signals/executed` | Confirm trade execution |
| POST | `/api/position-analysis` | Send open position for AI management |
| GET | `/api/signals/management?account={login}` | Poll position management action |
| POST | `/api/signals/management/applied` | Confirm management action applied |
| POST | `/api/trades/update` | Trade open/close updates |

All endpoints require header: `X-API-TOKEN: {MT5_API_TOKEN}`

## Configuration

Key `.env` variables:

```env
MT5_API_TOKEN=your-secret-token
AI_PROVIDER=openai          # openai | anthropic | gemini
OPENAI_API_KEY=sk-...
MIN_CONFIDENCE=80
MAX_OPEN_TRADES=3
RISK_PER_TRADE_PCT=1.0
MAX_DAILY_DRAWDOWN_PCT=3.0
TRADING_SYMBOLS=BTCUSDT,ETHUSDT,PAXGUSDT,XAUUSD,EURUSD,GBPUSD
```

## Development Milestones

- [x] M1 — MT5 ↔ Laravel communication
- [x] M2 — Laravel ↔ AI integration
- [x] M3 — Signal generation
- [x] M4 — MT5 auto execution
- [x] M5 — Trade synchronization
- [x] M6 — AI position management
- [x] M7 — Analytics dashboard (Phase 2)
- [ ] M8 — Multi-AI consensus (Phase 2)

## Risk Warning

Automated trading carries significant financial risk. Test thoroughly on a demo account before live deployment.
