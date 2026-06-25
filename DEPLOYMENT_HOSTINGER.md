# Deploy MT5 AI Trading Platform on Hostinger Shared Hosting

Complete step-by-step guide to deploy the Laravel API on Hostinger shared hosting and connect MetaTrader 5.

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Architecture on Shared Hosting](#2-architecture-on-shared-hosting)
3. [Prepare Project Locally](#3-prepare-project-locally)
4. [Create Hostinger Resources](#4-create-hostinger-resources)
5. [Upload Files](#5-upload-files)
6. [Configure Document Root](#6-configure-document-root)
7. [Install Dependencies via SSH](#7-install-dependencies-via-ssh)
8. [Configure Environment (.env)](#8-configure-environment-env)
9. [Run Migrations](#9-run-migrations)
10. [Set Folder Permissions](#10-set-folder-permissions)
11. [Enable SSL (HTTPS)](#11-enable-ssl-https)
12. [Configure PHP](#12-configure-php)
13. [Setup Queue Worker (Cron)](#13-setup-queue-worker-cron)
14. [Configure MetaTrader 5 EA](#14-configure-metatrader-5-ea)
15. [Test Deployment](#15-test-deployment)
16. [Maintenance Commands](#16-maintenance-commands)
17. [Troubleshooting](#17-troubleshooting)
18. [Security Checklist](#18-security-checklist)

---

## 1. Requirements

| Item | Minimum |
|------|---------|
| Hostinger plan | Premium, Business, or Cloud (SSH access recommended) |
| PHP | 8.2 or higher |
| Database | MySQL 8 (included with Hostinger) |
| Domain | e.g. `api.yourdomain.com` |
| SSL | Required for MT5 WebRequest |
| Local tools | FileZilla / WinSCP, optional Git |

**Important:** Shared hosting cannot run a permanent background process like `queue:work` 24/7. This guide uses a **cron job** to process the AI queue every minute.

---

## 2. Architecture on Shared Hosting

```
MT5 (your PC/VPS)
    │  HTTPS
    ▼
api.yourdomain.com  (Hostinger)
    ├── Laravel public/
    ├── MySQL database
    └── Cron → queue:work (every 1 min)
            └── AI API (OpenAI / Claude / Gemini)
```

Recommended subdomain: **`api.yourdomain.com`**  
API base URL for MT5: **`https://api.yourdomain.com/api`**

---

## 3. Prepare Project Locally

On your Windows/Mac development machine:

### 3.1 Install production dependencies

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### 3.2 Create deployment package

Upload these folders/files to Hostinger:

```
backend/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← document root must point here
├── resources/
├── routes/
├── storage/
├── artisan
├── composer.json
├── composer.lock
└── .env             ← create on server (do NOT upload local .env with secrets)
```

**Do NOT upload:**
- `vendor/` (install on server via Composer)
- `node_modules/`
- `.git/`
- `tests/`
- local `database/database.sqlite`

### 3.3 Zip for upload (optional)

```bash
# From project root — exclude dev files
cd backend
# Create zip manually or use 7-Zip excluding vendor, .git, tests, node_modules
```

---

## 4. Create Hostinger Resources

Log in to **Hostinger hPanel** → your website.

### 4.1 Create subdomain

1. Go to **Domains → Subdomains**
2. Create subdomain: `api`
3. Document root will be something like:
   ```
   /home/u123456789/domains/api.yourdomain.com/public_html
   ```

### 4.2 Create MySQL database

1. Go to **Databases → MySQL Databases**
2. Create database, e.g. `u123456789_mt5ai`
3. Create user, e.g. `u123456789_mt5user`
4. Set a strong password
5. Assign user to database with **All Privileges**
6. Note down:
   - Host: usually `localhost`
   - Database name
   - Username
   - Password

---

## 5. Upload Files

### Option A — File Manager (no SSH)

1. hPanel → **Files → File Manager**
2. Open subdomain folder: `domains/api.yourdomain.com/`
3. Delete default files inside `public_html` if empty setup
4. Upload project **one level above** `public_html`:

```
domains/api.yourdomain.com/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← contains index.php
├── routes/
├── storage/
├── artisan
├── composer.json
└── composer.lock
```

### Option B — FTP (FileZilla / WinSCP)

- Host: your domain or FTP hostname from hPanel
- Username / Password: from hPanel → **Files → FTP Accounts**
- Upload to same structure as above

### Option C — SSH + Git (recommended)

```bash
ssh u123456789@your-server.hostinger.com
cd ~/domains/api.yourdomain.com
git clone <your-repo-url> .
cd backend   # if repo has backend subfolder
```

---

## 6. Configure Document Root

Laravel must serve from the `public/` folder.

### Hostinger hPanel

1. Go to **Domains → Subdomains** (or **Websites → Manage**)
2. Edit subdomain `api.yourdomain.com`
3. Set **Document root** to:
   ```
   /home/u123456789/domains/api.yourdomain.com/public
   ```
   (Adjust path if you uploaded inside a `backend/` subfolder)

### Verify

After setup, visiting `https://api.yourdomain.com` should show the Laravel welcome page or a 404 — not a directory listing.

---

## 7. Install Dependencies via SSH

### 7.1 Enable SSH

hPanel → **Advanced → SSH Access** → Enable

Connect:

```bash
ssh u123456789@your-server.hostinger.com
cd ~/domains/api.yourdomain.com
```

(Use your actual path — check in File Manager.)

### 7.2 Install Composer dependencies

```bash
# Hostinger PHP 8.2+ path (check in hPanel → PHP Configuration)
/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

If `composer` is not found:

```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

### 7.3 Generate application key

```bash
php artisan key:generate
```

---

## 8. Configure Environment (.env)

Create `.env` on the server (copy from `.env.example`):

```bash
cp .env.example .env
nano .env
```

### Production `.env` example

```env
APP_NAME="MT5 AI Trading"
APP_ENV=production
APP_KEY=                        # filled by key:generate
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# MySQL (from Hostinger hPanel)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_mt5ai
DB_USERNAME=u123456789_mt5user
DB_PASSWORD=your_strong_db_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# MT5 API security — use a long random string
MT5_API_TOKEN=your-very-long-random-secret-token-here

# Trading symbols
TRADING_SYMBOLS=BTCUSDT,ETHUSDT,PAXGUSDT,XAUUSD,EURUSD,GBPUSD
TRADING_CANDLE_COUNT=50

# AI Provider: openai | anthropic | gemini
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4o-mini

# Optional: Claude or Gemini instead
# AI_PROVIDER=anthropic
# ANTHROPIC_API_KEY=sk-ant-...
# AI_PROVIDER=gemini
# GEMINI_API_KEY=...

# Risk management
RISK_PER_TRADE_PCT=1.0
MIN_CONFIDENCE=80
MAX_OPEN_TRADES=3
MAX_DAILY_DRAWDOWN_PCT=3.0
TRADING_SESSIONS=00:00-23:59
```

### Generate a secure API token

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Use the output as `MT5_API_TOKEN`.

---

## 9. Run Migrations

```bash
php artisan migrate --force
```

Verify tables:

```bash
php artisan db:show
```

You should see: `accounts`, `signals`, `trades`, `market_snapshots`, `jobs`, etc.

---

## 10. Set Folder Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R u123456789:u123456789 storage bootstrap/cache
```

(Replace `u123456789` with your Hostinger username.)

If you get permission errors in logs:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## 11. Enable SSL (HTTPS)

MT5 **requires HTTPS** for WebRequest in production.

1. hPanel → **Security → SSL**
2. Select domain `api.yourdomain.com`
3. Install free **Let's Encrypt** certificate
4. Enable **Force HTTPS** (or add redirect in hPanel)

Verify: open `https://api.yourdomain.com/up` — should return `{"status":"ok"}` or similar health response.

---

## 12. Configure PHP

hPanel → **Advanced → PHP Configuration** → select `api.yourdomain.com`

| Setting | Value |
|---------|-------|
| PHP version | 8.2 or 8.3 |
| `max_execution_time` | 120 |
| `memory_limit` | 256M |
| `upload_max_filesize` | 10M |

### Required PHP extensions

Ensure these are enabled:

- `curl` (AI API calls)
- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`

---

## 13. Setup Queue Worker (Cron)

AI processing runs asynchronously. On shared hosting, use a cron job.

### 13.1 hPanel Cron Job

hPanel → **Advanced → Cron Jobs** → **Create**

| Field | Value |
|-------|-------|
| Schedule | Every minute (`* * * * *`) |
| Command | See below |

**Command (adjust paths):**

```bash
/usr/bin/php /home/u123456789/domains/api.yourdomain.com/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

If project is in `backend/` subfolder:

```bash
/usr/bin/php /home/u123456789/domains/api.yourdomain.com/backend/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

### 13.2 Optional: Laravel scheduler cron

Add a second cron (also every minute):

```bash
/usr/bin/php /home/u123456789/domains/api.yourdomain.com/artisan schedule:run >> /dev/null 2>&1
```

### 13.3 Verify queue is processing

1. Send test market data (see [Test Deployment](#15-test-deployment))
2. Check jobs table:

```bash
php artisan tinker
>>> \DB::table('jobs')->count();
>>> \App\Models\MarketSnapshot::count();
```

If `jobs` count stays high and never decreases, the cron job path or PHP binary is wrong.

---

## 14. Configure MetaTrader 5 EA

### 14.1 Install EA

1. Copy `mt5/AI_Trading_EA.mq5` to:
   ```
   C:\Users\<You>\AppData\Roaming\MetaQuotes\Terminal\<ID>\MQL5\Experts\
   ```
2. Open **MetaEditor** → Compile (F7)

### 14.2 Allow WebRequest URL

MT5 → **Tools → Options → Expert Advisors**

- ✅ Allow algorithmic trading
- ✅ Allow WebRequest for listed URL

Add:

```
https://api.yourdomain.com
```

### 14.3 EA Inputs

Attach EA to a chart and set:

| Input | Value |
|-------|-------|
| `InpApiBaseUrl` | `https://api.yourdomain.com/api` |
| `InpApiToken` | Same as `MT5_API_TOKEN` in `.env` |
| `InpPollIntervalSec` | `7` |
| `InpMinConfidence` | `80` |
| `InpSymbols` | `XAUUSD,EURUSD,GBPUSD` |
| `InpTimeframe` | `M15` or `H1` |

### 14.4 Test on demo first

Always test on a **demo account** before live trading.

---

## 15. Test Deployment

### 15.1 Health check

```bash
curl https://api.yourdomain.com/up
```

### 15.2 Test market-data endpoint

Replace `YOUR_TOKEN` with your `MT5_API_TOKEN`:

```bash
curl -X POST https://api.yourdomain.com/api/market-data \
  -H "Content-Type: application/json" \
  -H "X-API-TOKEN: YOUR_TOKEN" \
  -d "{\"account\":{\"login\":123456,\"balance\":10000,\"equity\":10000,\"free_margin\":9500},\"symbols\":[{\"symbol\":\"XAUUSD\",\"timeframe\":\"M15\",\"indicators\":{\"ema20\":3350,\"ema50\":3340,\"ema200\":3300,\"rsi\":64,\"atr\":12},\"candles\":[{\"time\":\"2026-06-24 10:00\",\"open\":3350,\"high\":3360,\"low\":3345,\"close\":3358,\"volume\":1000}]}]}"
```

Expected response:

```json
{"status":"accepted"}
```

### 15.3 Test signals endpoint

```bash
curl "https://api.yourdomain.com/api/signals?account=123456" \
  -H "X-API-TOKEN: YOUR_TOKEN"
```

After cron processes the queue and AI responds:

```json
{"id":1,"symbol":"XAUUSD","action":"BUY","confidence":85,...}
```

Or if no signal yet:

```json
{"status":"NO_SIGNAL"}
```

### 15.4 Check logs on server

```bash
tail -f storage/logs/laravel.log
```

### 15.5 Check database

```bash
php artisan tinker
>>> \App\Models\Account::all();
>>> \App\Models\Signal::latest()->get();
>>> \App\Models\MarketSnapshot::count();
```

---

## 16. Maintenance Commands

Run via SSH when needed:

```bash
# Clear config cache after .env changes
php artisan config:clear
php artisan config:cache

# Clear route cache
php artisan route:cache

# Run new migrations after updates
php artisan migrate --force

# View failed queue jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Put site in maintenance mode
php artisan down

# Bring site back
php artisan up
```

### Deploying updates

```bash
cd ~/domains/api.yourdomain.com
git pull                          # or upload changed files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

---

## 17. Troubleshooting

### 500 Internal Server Error

1. Check `storage/logs/laravel.log`
2. Verify permissions on `storage/` and `bootstrap/cache/`
3. Ensure `APP_KEY` is set: `php artisan key:generate`
4. Set `APP_DEBUG=true` temporarily to see error (set back to `false` after)

### 401 Unauthorized from API

- `X-API-TOKEN` header must match `MT5_API_TOKEN` in `.env`
- Run `php artisan config:clear` after changing `.env`

### MT5 WebRequest failed / Error 4014

- Add exact URL to MT5 allowed list: `https://api.yourdomain.com`
- Must use **HTTPS** (not HTTP)
- Check firewall on your PC

### AI signals not generated

1. Cron job running? Check hPanel → Cron Jobs → logs
2. Queue stuck? `php artisan queue:failed`
3. AI key valid? Check `storage/logs/laravel.log` for API errors
4. `OPENAI_API_KEY` (or other provider key) set in `.env`

### Database connection error

- Use `localhost` as `DB_HOST` on Hostinger (not `127.0.0.1` in some cases both work)
- Verify database name, username, password in hPanel
- User must have privileges on the database

### Composer memory error

```bash
php -d memory_limit=-1 /usr/local/bin/composer install --no-dev
```

### Slow AI responses / timeout

- Increase PHP `max_execution_time` to 120
- Cron `--max-time=55` processes one batch per minute — normal for shared hosting
- Consider Hostinger VPS if you need real-time queue workers

---

## 18. Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] Strong `MT5_API_TOKEN` (32+ random characters)
- [ ] HTTPS enabled and forced
- [ ] `.env` file is NOT publicly accessible
- [ ] AI API keys stored only in `.env` (never in Git)
- [ ] Database user has access only to your app database
- [ ] Test on demo account before live trading
- [ ] Restrict subdomain `api.*` to API use only (no public admin)

### Block direct .env access

Laravel's `public/.htaccess` already blocks most sensitive paths. Ensure document root is `public/` only — never the project root.

---

## Quick Reference

| Item | Example value |
|------|---------------|
| API URL | `https://api.yourdomain.com/api` |
| Health check | `https://api.yourdomain.com/up` |
| MT5 header | `X-API-TOKEN: your-secret` |
| Cron (every min) | `php artisan queue:work --stop-when-empty --max-time=55` |
| Logs | `storage/logs/laravel.log` |
| Database | MySQL via hPanel |

---

## Folder Structure on Server (Final)

```
/home/u123456789/domains/api.yourdomain.com/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/                 ← document root
│   ├── index.php
│   └── .htaccess
├── routes/
├── storage/
│   └── logs/
│       └── laravel.log
├── vendor/
├── .env                    ← production config (not in Git)
├── artisan
└── composer.json
```

---

## Support Notes

- **Hostinger shared hosting** is suitable for the API and cron-based queue processing.
- For **high-frequency trading** or **always-on queue workers**, upgrade to Hostinger VPS and use Supervisor instead of cron.
- MT5 EA runs on your Windows PC or a VPS — it does not run on Hostinger.
