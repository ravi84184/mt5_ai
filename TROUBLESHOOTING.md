# No Signals — Troubleshooting Guide

Signal flow:

```
MT5 EA (new candle) → POST /api/market-data → Queue job → AI → signals table
MT5 EA (every 7s)   → GET  /api/signals     → execute trade
```

Check each step in order.

---

## Step 1 — MT5 Experts tab (your PC)

Open MT5 → **View → Toolbox → Experts**

Look for:

| Message | Meaning |
|---------|---------|
| `AI Trading EA started. Symbols: 3` | EA loaded OK |
| `Market data sent: {"status":"accepted"}` | API received data |
| `WebRequest POST failed ... Error: 4014` | URL not in allowed list |
| `WebRequest POST failed ... Error: 5203` | HTTP error / SSL / wrong URL |
| No messages at all | EA not running or AutoTrading off |

**Fix 4014:** Tools → Options → Expert Advisors → add:
```
https://mt5-ai.niksofts.com
```

**EA inputs must match server:**
- `InpApiBaseUrl` = `https://mt5-ai.niksofts.com/api`
- `InpApiToken` = same as `MT5_API_TOKEN` in server `.env`

**Enable:** AutoTrading button ON (green) in MT5 toolbar.

**Note:** Market data is sent on each **new candle** (M15/H1). After attaching EA, wait for next candle close OR re-attach after EA update (sends on start).

---

## Step 2 — Test API from your PC

Replace `YOUR_TOKEN` and account login:

```powershell
# Health
curl https://mt5-ai.niksofts.com/up

# Poll signals (use your MT5 account number)
curl "https://mt5-ai.niksofts.com/api/signals?account=12345678" -H "X-API-TOKEN: YOUR_TOKEN"

# Send test market data
curl -X POST https://mt5-ai.niksofts.com/api/market-data -H "Content-Type: application/json" -H "X-API-TOKEN: YOUR_TOKEN" -d "{\"account\":{\"login\":12345678,\"balance\":10000,\"equity\":10000,\"free_margin\":9500},\"symbols\":[{\"symbol\":\"XAUUSD\",\"timeframe\":\"M15\",\"indicators\":{\"ema20\":3350,\"ema50\":3340,\"ema200\":3300,\"rsi\":64,\"atr\":12},\"candles\":[{\"time\":\"2026-06-24 10:00\",\"open\":3350,\"high\":3360,\"low\":3345,\"close\":3358,\"volume\":1000}]}]}"
```

Expected:
- market-data → `{"status":"accepted"}`
- signals → `{"status":"NO_SIGNAL"}` or a BUY/SELL object

---

## Step 3 — Server: queue worker running?

SSH into EC2:

```bash
cd /var/www/mt5_ai/backend

# Quick diagnose command
php artisan mt5:diagnose

# Supervisor status
sudo supervisorctl status

# Worker log
tail -50 storage/logs/worker.log

# Laravel errors
tail -50 storage/logs/laravel.log
```

**If queue is not running**, jobs pile up and AI never runs:

```bash
sudo supervisorctl start mt5-ai-worker:*
sudo supervisorctl status
```

Manual one-off processing:

```bash
php artisan queue:work --once
```

---

## Step 4 — Check database

```bash
cd /var/www/mt5_ai/backend
php artisan tinker
```

```php
// Did MT5 send data?
\App\Models\Account::all();
\App\Models\MarketSnapshot::count();
\App\Models\MarketSnapshot::latest()->first();

// Signals?
\App\Models\Signal::latest()->take(5)->get(['id','symbol','action','confidence','status','reason']);

// Stuck jobs?
\DB::table('jobs')->count();
\DB::table('failed_jobs')->get();

// AI input/output logs
\App\Models\AiInteractionLog::latest()->take(3)->get(['id','symbol','status','created_at']);
```

**View AI logs via CLI:**

```bash
php artisan ai:logs --limit=10
php artisan ai:logs --id=59           # full prompts + response
php artisan ai:logs --symbol=XAUUSD --type=entry
```

| What you see | Problem |
|--------------|---------|
| No accounts | MT5 never reached API (WebRequest / URL / token) |
| Accounts but no snapshots | market-data failed validation |
| Snapshots but jobs > 0 | Queue worker not running |
| Signals with `WAIT` | AI said wait — normal, MT5 won't trade |
| Signals `REJECTED` | Failed risk rules (confidence, max trades, session) |
| Signals `PENDING` + BUY/SELL | Should appear in MT5 poll — check account login matches |
| failed_jobs rows | AI API error — check laravel.log or `ai:logs` |

---

## Step 5 — AI API key

On server `.env`:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

Test:

```bash
grep OPENAI_API_KEY .env   # must not be empty
php artisan config:clear
php artisan queue:work --once
tail -20 storage/logs/laravel.log
```

Look for: `AI entry analysis failed` in logs.

---

## Step 6 — Account login must match

MT5 polls: `/api/signals?account=YOUR_MT5_LOGIN`

The login in `accounts.mt5_login` must **exactly match** your MT5 account number.

```php
\App\Models\Account::pluck('mt5_login');
```

Compare with MT5: **File → Login to trade account** (account number).

---

## Step 7 — Why signal exists but no trade

MT5 EA rejects locally if:

| Reason | Experts tab message |
|--------|---------------------|
| confidence < 80 | `Signal rejected: confidence ...` |
| Max open trades | `Signal rejected: max open trades` |
| Position already open | `Signal rejected: position already open` |
| action is WAIT | Not returned by API (filtered server-side) |
| Symbol not in Market Watch | Order fails silently — add symbol |
| AutoTrading off | No execution |

---

## Step 8 — Symbol names

Broker symbols may differ: `XAUUSD` vs `XAUUSD.m` vs `GOLD`.

EA `InpSymbols` must match **exact broker symbol names**.

In MT5 Market Watch, right-click symbol → Specification → use exact name.

---

## Manual AI request (no wait for candle)

### Option A — Chart buttons (EA)

Recompile `AI_Trading_EA.mq5` and re-attach to chart. Two buttons appear top-left:

| Button | Action |
|--------|--------|
| **Ask AI Entry** | Sends market data now → AI entry signal (BUY/SELL/WAIT) |
| **Manage Open** | Sends open positions → AI management (HOLD/CLOSE/MOVE_SL) |

Experts tab should show: `Manual AI entry analysis requested` then `Market data sent: {"status":"accepted"}`.

Wait ~30–60 seconds for queue + AI, then EA polls signal automatically.

Hide buttons: set EA input `InpShowButtons = false`.

### Option B — One-click Script

1. Copy `mt5/AI_Manual_Ask.mq5` to `MQL5/Scripts/`
2. Compile in MetaEditor
3. Navigator → **Scripts → AI_Manual_Ask** → drag onto chart
4. Set API URL, token, symbols → OK

Runs once immediately. Enable `InpManageOpenPos` to also analyze open trades.

---

## Quick checklist

- [ ] AutoTrading ON in MT5
- [ ] WebRequest URL allowed (HTTPS, no `/api` suffix)
- [ ] `InpApiBaseUrl` ends with `/api`
- [ ] `InpApiToken` = server `MT5_API_TOKEN`
- [ ] Supervisor workers RUNNING
- [ ] `OPENAI_API_KEY` set in `.env`
- [ ] `market_snapshots` table has rows
- [ ] `signals` has PENDING BUY/SELL (not only WAIT/REJECTED)
- [ ] MT5 account login matches `accounts.mt5_login`
- [ ] Symbol names match broker

---

## Dashboard 404 at `/dashboard`

If `https://mt5-ai.niksofts.com/up` works but `/dashboard` returns 404, the server does not have the latest code or route cache is stale.

**Symptom:** `head routes/web.php` shows dashboard routes, but `php artisan route:list | grep dashboard` is **empty** and `/` still shows the Laravel welcome page.

**Cause:** Usually a **stale route cache** at `bootstrap/cache/routes-v7.php`. When this file exists, Laravel ignores `routes/web.php` entirely and serves old routes (welcome page at `/`, no dashboard).

`route:clear` and PHP-FPM restart may not delete the file if permissions are wrong.

**On the server (SSH):**

```bash
cd /var/www/mt5_ai/backend

# 1. Check for stale route cache (most common cause)
ls -la bootstrap/cache/routes-v7.php

# 2. Force-delete all bootstrap caches
sudo rm -f bootstrap/cache/routes-v7.php bootstrap/cache/config.php bootstrap/cache/services.php
php artisan route:clear
php artisan optimize:clear

# 3. Pull latest code (includes mt5:routes-fix helper)
git pull origin main
composer dump-autoload

# 4. Verify dashboard files exist
ls -la app/Http/Controllers/Dashboard/

# 5. Verify routes (run in a NEW command after cache delete)
php artisan route:list | grep dashboard

# 6. Restart PHP-FPM
sudo systemctl restart php8.5-fpm
```

Or use the fix command (after git pull):

```bash
php artisan mt5:routes-fix
php artisan route:list | grep dashboard
sudo systemctl restart php8.5-fpm
```

You should see routes like `dashboard.login`, `dashboard.index`, etc.

**In `.env` add:**

```env
DASHBOARD_PASSWORD=your-secure-password
```

Then test:

```bash
curl -I https://mt5-ai.niksofts.com/dashboard/login
```

Expected: `HTTP/1.1 200` (login page), not `404`.

**No npm needed.** The dashboard is plain Laravel Blade + `public/css/dashboard.css`.

**If routes still missing after FPM restart**, nginx may point at a different app directory:

```bash
sudo nginx -T 2>/dev/null | grep "root "
```

Ensure `root` is `/var/www/mt5_ai/backend/public` (or your actual deploy path).

---

## Common fixes

```bash
# On server
cd /var/www/mt5_ai/backend
php artisan config:clear
php artisan cache:clear
php artisan queue:restart
sudo supervisorctl restart mt5-ai-worker:*
php artisan mt5:diagnose
```
