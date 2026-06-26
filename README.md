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
# Set ADMIN_PASSWORD, then configure AI keys in Super Admin after migrate

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
   - `InpApiBaseUrl` — Laravel API URL (must end with `/api`)
   - `InpApiToken` — per-account token from Super Admin (Accounts → Generate API token)
   - `InpUseServerConfig` — `true` to load symbols, AI provider, and risk limits from Super Admin
   - `InpSymbols` — fallback only when `InpAllowSymbolFallback=true` and admin has no symbols
   - `InpMinConfidence` / `InpMaxOpenTrades` — local fallback if admin has no override
6. Configure the account in Super Admin (`/admin/accounts`): symbols, AI provider, trading on/off
7. **Manual AI request:** click **Ask AI Entry** on chart, or run script `AI_Manual_Ask.mq5`

### 3. Production

- **AWS (EC2, MySQL on server):** see [DEPLOYMENT_AWS.md](DEPLOYMENT_AWS.md)
- **Hostinger shared hosting:** see [DEPLOYMENT_HOSTINGER.md](DEPLOYMENT_HOSTINGER.md)
- **No signals?** see [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

### 4. Super Admin

Full control panel at `/admin` (password-protected). Manages accounts, AI providers, symbols, signals, trades, queue, and system config.

**Trading settings** (AI keys, risk limits, default symbols): `/admin/system/settings`

```env
ADMIN_PASSWORD=your-secure-password
```

Legacy `DASHBOARD_PASSWORD` still works if `ADMIN_PASSWORD` is not set.

After deploy:

```bash
cd backend
php artisan migrate --force
php artisan optimize:clear
sudo systemctl restart php8.5-fpm
```

Open `https://your-domain.com/admin`

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/account-config?account={login}` | EA fetches admin-configured symbols & settings |
| POST | `/api/market-data` | Receive candles + indicators (async AI) |
| GET | `/api/signals?account={login}` | Poll pending trade signal |
| POST | `/api/signals/executed` | Confirm trade execution |
| POST | `/api/position-analysis` | Send open position for AI management |
| GET | `/api/signals/management?account={login}` | Poll position management action |
| POST | `/api/signals/management/applied` | Confirm management action applied |
| POST | `/api/trades/update` | Trade open/close updates |

All endpoints require header: `X-API-TOKEN: {per-account token}` (generated in Super Admin) or legacy global `MT5_API_TOKEN`

## Configuration

**Super Admin → System → Trading settings** (`/admin/system/settings`):

- AI provider and API keys (OpenAI, Anthropic, Gemini)
- Default symbols and candle count
- Risk limits (confidence, max trades, drawdown, sessions)

`.env` fallbacks (optional — used only when not saved in admin):

```env
ADMIN_PASSWORD=your-secure-password
MT5_API_TOKEN=your-secret-token  # optional legacy global token
```

## Development Milestones

- [x] M1 — MT5 ↔ Laravel communication
- [x] M2 — Laravel ↔ AI integration
- [x] M3 — Signal generation
- [x] M4 — MT5 auto execution
- [x] M5 — Trade synchronization
- [x] M6 — AI position management
- [x] M7 — Super Admin panel (accounts, trades, system, queue)
- [ ] M8 — Multi-AI consensus (Phase 2)

## Risk Warning

Automated trading carries significant financial risk. Test thoroughly on a demo account before live deployment.
