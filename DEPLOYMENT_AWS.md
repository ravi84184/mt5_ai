# Deploy MT5 AI Trading Platform on AWS

Complete step-by-step guide to deploy the Laravel API on a single **EC2 instance** (Nginx + PHP + MySQL on the same server) and connect MetaTrader 5.

> **No RDS** — MySQL runs locally on EC2. Simpler setup, lower cost.

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Architecture on AWS](#2-architecture-on-aws)
3. [AWS Services Used](#3-aws-services-used)
4. [Prepare Project Locally](#4-prepare-project-locally)
5. [Create Security Group](#5-create-security-group)
6. [Launch EC2 Instance](#6-launch-ec2-instance)
7. [Connect to EC2 via SSH](#7-connect-to-ec2-via-ssh)
8. [Install Server Software](#8-install-server-software)
9. [Install & Configure MySQL](#9-install--configure-mysql)
10. [Deploy Application Code](#10-deploy-application-code)
11. [Configure Nginx](#11-configure-nginx)
12. [Configure Environment (.env)](#12-configure-environment-env)
13. [Run Migrations](#13-run-migrations)
14. [Setup Supervisor (Queue Worker)](#14-setup-supervisor-queue-worker)
15. [Configure Domain & SSL](#15-configure-domain--ssl)
16. [Configure MetaTrader 5 EA](#16-configure-metatrader-5-ea)
17. [Test Deployment](#17-test-deployment)
18. [Database Backups](#18-database-backups)
19. [CI/CD with GitHub Actions (Optional)](#19-cicd-with-github-actions-optional)
20. [Monitoring & Logs](#20-monitoring--logs)
21. [Maintenance Commands](#21-maintenance-commands)
22. [Cost Estimate](#22-cost-estimate)
23. [Troubleshooting](#23-troubleshooting)
24. [Security Checklist](#24-security-checklist)
25. [Alternative: AWS Lightsail](#25-alternative-aws-lightsail)

---

## 1. Requirements

| Item         | Minimum                                     |
| ------------ | ------------------------------------------- |
| AWS account  | With billing enabled                        |
| Region       | e.g. `us-east-1`, `ap-south-1`, `eu-west-1` |
| Domain       | e.g. `api.yourdomain.com`                   |
| EC2 instance | `t3.small` (2 vCPU, 2 GB RAM) recommended   |
| Storage      | 30 GB gp3 (app + MySQL data)                |
| PHP          | 8.2 or higher                               |
| MySQL        | 8.0 (installed on EC2)                      |
| SSL          | Required for MT5 WebRequest                 |
| Local tools  | SSH client, Git, optional AWS CLI           |

**Why AWS over shared hosting:** EC2 runs a **persistent queue worker** via Supervisor — AI jobs process immediately instead of waiting for cron.

---

## 2. Architecture on AWS

```
MT5 (your PC / Windows VPS)
        │  HTTPS
        ▼
Route 53  →  api.yourdomain.com
        │
        ▼
EC2 (Ubuntu 24.04) — single server
├── Nginx
├── PHP 8.3-FPM
├── MySQL 8.0          ← local, not RDS
├── Laravel (backend/)
└── Supervisor → queue:work (always on)
        │
        └──► AI APIs (OpenAI / Claude / Gemini)
```

**API base URL for MT5:** `https://api.yourdomain.com/api`

---

## 3. AWS Services Used

| Service             | Purpose                                             |
| ------------------- | --------------------------------------------------- |
| **EC2**             | Runs Laravel, Nginx, PHP, MySQL, Supervisor         |
| **Route 53**        | DNS for `api.yourdomain.com` (or use any registrar) |
| **Security Groups** | Firewall rules                                      |
| **Elastic IP**      | Static public IP (recommended)                      |

Optional upgrades:

- **ALB** + **ACM** for managed HTTPS at scale
- **S3** for database backup storage
- **CloudWatch** for logs and alarms

---

## 4. Prepare Project Locally

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Ensure your code is in Git (GitHub, GitLab, or CodeCommit). You will clone it on the server.

**Do not commit:**

- `.env`
- `vendor/` (install on server)
- `database/database.sqlite`

---

## 5. Create Security Group

AWS Console → **EC2 → Security Groups → Create**

| Setting  | Value                       |
| -------- | --------------------------- |
| Name     | `mt5-ai-ec2-sg`             |
| Inbound  | SSH (22) — **your IP only** |
| Inbound  | HTTP (80) — `0.0.0.0/0`     |
| Inbound  | HTTPS (443) — `0.0.0.0/0`   |
| Outbound | All traffic                 |

> **Do not** open port 3306 (MySQL) to the internet. MySQL listens on `localhost` only.

---

## 6. Launch EC2 Instance

AWS Console → **EC2 → Launch instance**

| Setting        | Value                             |
| -------------- | --------------------------------- |
| Name           | `mt5-ai-api`                      |
| AMI            | Ubuntu Server 24.04 LTS           |
| Instance type  | `t3.small`                        |
| Key pair       | Create new → download `.pem` file |
| Security group | `mt5-ai-ec2-sg`                   |
| Storage        | **30 GB** gp3                     |

Launch the instance, then attach an **Elastic IP**:

1. EC2 → **Elastic IPs → Allocate**
2. **Associate** with your instance

---

## 7. Connect to EC2 via SSH

### Windows (PowerShell)

```powershell
ssh -i "C:\path\to\mt5-ai-key.pem" ubuntu@<EC2_PUBLIC_IP>
```

### macOS / Linux

```bash
chmod 400 mt5-ai-key.pem
ssh -i mt5-ai-key.pem ubuntu@<EC2_PUBLIC_IP>
```

---

## 8. Install Server Software

Run on EC2 as `ubuntu`:

### 8.1 Check PHP version

```bash
lsb_release -a
php -v
```

Install packages that **match your PHP version**. The number in `php -v` (e.g. `8.5.4`) determines the package prefix:

| `php -v` shows | Package prefix | Nginx socket      |
| -------------- | -------------- | ----------------- |
| PHP 8.5.x      | `php8.5-*`     | `php8.5-fpm.sock` |
| PHP 8.4.x      | `php8.4-*`     | `php8.4-fpm.sock` |
| PHP 8.3.x      | `php8.3-*`     | `php8.3-fpm.sock` |
| PHP 8.2.x      | `php8.2-*`     | `php8.2-fpm.sock` |

| Ubuntu version | Default PHP | Notes                                 |
| -------------- | ----------- | ------------------------------------- |
| **24.10+**     | 8.5         | Use `php8.5-*` packages               |
| **24.04**      | 8.3         | Use `php8.3-*` packages               |
| **22.04**      | 8.1         | Add PPA (step 8.2), then install 8.3+ |

Laravel requires **PHP 8.2 or higher**. PHP 8.5 is fine.

### 8.2 Add PHP repository (Ubuntu 22.04 only)

If no suitable PHP version is available, run:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
```

### 8.3 Install Nginx, PHP extensions, Supervisor

Replace `8.5` with your version if different (e.g. `8.3`, `8.4`):

```bash
sudo apt install -y nginx php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd git unzip supervisor
```

Or auto-detect version (if `php` CLI is already installed):

```bash
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sudo apt install -y nginx php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-mysql \
  php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip \
  php${PHP_VER}-bcmath php${PHP_VER}-intl php${PHP_VER}-gd git unzip supervisor
echo "Using PHP $PHP_VER — Nginx socket: /var/run/php/php${PHP_VER}-fpm.sock"
```

> Use the same version in the Nginx config (section 11) and all `systemctl` commands (e.g. `php8.5-fpm`).

### 8.4 Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 8.5 Verify

```bash
php -v          # e.g. PHP 8.5.4
php -m | grep -E 'mysql|mbstring|curl|zip|bcmath'
composer -V
nginx -v
sudo systemctl status php8.5-fpm    # use your version: php8.3-fpm, php8.5-fpm, etc.
```

---

## 9. Install & Configure MySQL

### 9.1 Install MySQL 8

```bash
sudo apt install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql
```

### 9.2 Secure MySQL

```bash
sudo mysql_secure_installation
```

Recommended answers:

- Validate password plugin: **Y** (or N for simpler dev setup)
- Set root password: **Y** — choose a strong password
- Remove anonymous users: **Y**
- Disallow root login remotely: **Y**
- Remove test database: **Y**
- Reload privileges: **Y**

### 9.3 Create database and user

```bash
sudo mysql -u root -p
```

Run in MySQL shell:

```sql
CREATE DATABASE mt5_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'mt5user'@'localhost' IDENTIFIED BY 'R@vi84184';

GRANT ALL PRIVILEGES ON mt5_ai.* TO 'mt5user'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```

### 9.4 Verify MySQL is local-only

```bash
sudo mysql -u mt5user -p -e "SELECT 1;" mt5_ai
```

Check bind address (must be `127.0.0.1`):

```bash
sudo grep bind-address /etc/mysql/mysql.conf.d/mysqld.cnf
```

Should show:

```
bind-address = 127.0.0.1
```

If changed, restart MySQL:

```bash
sudo systemctl restart mysql
```

---

## 10. Deploy Application Code

### 10.1 Clone repository

```bash
sudo mkdir -p /var/www
sudo chown ubuntu:ubuntu /var/www
cd /var/www

git clone https://github.com/ravi84184/mt5_ai.git
cd mt5_ai/backend
```

If repo is private, use a deploy key or personal access token.

### 10.2 Install dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 10.3 Set permissions

```bash
sudo chown -R www-data:www-data /var/www/mt5_ai/backend/storage
sudo chown -R www-data:www-data /var/www/mt5_ai/backend/bootstrap/cache
sudo chmod -R 775 /var/www/mt5_ai/backend/storage
sudo chmod -R 775 /var/www/mt5_ai/backend/bootstrap/cache
```

---

## 11. Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/mt5-ai
```

Paste:

```nginx
server {
    listen 80;
    server_name mt5-ai.niksofts.com;
    root /var/www/mt5_ai/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;   # match your PHP version
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/mt5-ai /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 12. Configure Environment (.env)

```bash
cd /var/www/mt5_ai/backend
cp .env.example .env
nano .env
```

### Production `.env`

```env
APP_NAME="MT5 AI Trading"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://mt5-ai.niksofts.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# Local MySQL on EC2
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mt5_ai
DB_USERNAME=mt5user
DB_PASSWORD=R@vi84184

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# MT5 API
MT5_API_TOKEN=f346b6da84c7884e912b50ad257f40c880398071fcf739f13ea0327ceb9fa42b

# Trading
TRADING_SYMBOLS=BTCUSDT,ETHUSDT,PAXGUSDT,XAUUSD,EURUSD,GBPUSD
TRADING_CANDLE_COUNT=50

# AI
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4o-mini

# Risk
RISK_PER_TRADE_PCT=1.0
MIN_CONFIDENCE=80
MAX_OPEN_TRADES=3
MAX_DAILY_DRAWDOWN_PCT=3.0
TRADING_SESSIONS=00:00-23:59
```

Generate keys:

```bash
php artisan key:generate

# Secure API token
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Cache config:

```bash
php artisan config:cache
php artisan route:cache
```

---

## 13. Run Migrations

```bash
cd /var/www/mt5_ai/backend
php artisan migrate --force
php artisan db:show
```

You should see tables: `accounts`, `signals`, `trades`, `jobs`, etc.

If connection fails, check:

- MySQL is running: `sudo systemctl status mysql`
- Credentials in `.env` match section 9.3
- `DB_HOST=127.0.0.1` (not the EC2 public IP)

---

## 14. Setup Supervisor (Queue Worker)

Supervisor keeps `queue:work` running 24/7.

```bash
sudo nano /etc/supervisor/conf.d/mt5-ai-worker.conf
```

Paste:

```ini
[program:mt5-ai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mt5_ai/backend/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mt5_ai/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Start worker:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mt5-ai-worker:*
sudo supervisorctl status
```

After code updates:

```bash
php artisan queue:restart
sudo supervisorctl restart mt5-ai-worker:*
```

---

## 15. Configure Domain & SSL

### 15.1 Get your EC2 public IP (Elastic IP)

AWS Console → **EC2 → Elastic IPs** (or your instance details).

Note the **Elastic IP**, e.g. `3.110.45.67`.

> Use an **Elastic IP**, not the temporary IP — the temporary IP changes when you stop/start the instance.

Security group must allow **HTTP (80)** and **HTTPS (443)** from `0.0.0.0/0`.

---

### 15.2 Connect Hostinger subdomain to AWS EC2

Your domain DNS stays on **Hostinger**. You only point the subdomain to AWS — no need for Route 53.

#### Step 1 — Open Hostinger DNS

1. Log in to [Hostinger hPanel](https://hpanel.hostinger.com)
2. Go to **Domains** → select your domain (e.g. `niksofts.com`)
3. Open **DNS / DNS Zone / Manage DNS records**

#### Step 2 — Add an A record

| Field                 | Value                         | Example           |
| --------------------- | ----------------------------- | ----------------- |
| **Type**              | `A`                           | A                 |
| **Name**              | subdomain only (not full URL) | `api` or `mt5-ai` |
| **Points to / Value** | EC2 Elastic IP                | `3.110.45.67`     |
| **TTL**               | Default (300–14400)           | 3600              |

This creates: `api.yourdomain.com` → your EC2 server.

**Examples:**

| You want              | Name field | Result                |
| --------------------- | ---------- | --------------------- |
| `api.niksofts.com`    | `api`      | `api.niksofts.com`    |
| `mt5-ai.niksofts.com` | `mt5-ai`   | `mt5-ai.niksofts.com` |

> Do **not** enter `https://` or the full domain in the Name field — only the subdomain part.

#### Step 3 — Remove conflicts (if any)

Delete or edit old records for the same subdomain:

- Old **A** record pointing elsewhere
- **CNAME** on the same name (A and CNAME cannot coexist for one name)

#### Step 4 — Wait for DNS propagation

Usually **5–30 minutes**, sometimes up to 24 hours.

Check from your PC:

```bash
# Windows PowerShell
nslookup api.yourdomain.com

# Or
ping api.yourdomain.com
```

The IP should match your EC2 Elastic IP.

Online check: [https://dnschecker.org](https://dnschecker.org)

#### Step 5 — Update Nginx on EC2

`server_name` must match your subdomain:

```bash
sudo nano /etc/nginx/sites-available/mt5-ai
```

```nginx
server_name api.yourdomain.com;   # or mt5-ai.yourdomain.com
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Update `.env` on the server:

```env
APP_URL=https://api.yourdomain.com
```

```bash
php artisan config:cache
```

---

### 15.3 SSL with Certbot (after DNS works)

DNS must resolve to EC2 **before** running Certbot.

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d mt5-ai.niksofts.com
```

Replace with your actual subdomain (e.g. `-d mt5-ai.niksofts.com`).

Test auto-renewal:

```bash
sudo certbot renew --dry-run
```

Verify:

```bash
curl https://api.yourdomain.com/up
```

---

### 15.4 Flow summary

```
Hostinger DNS                    AWS EC2
─────────────                    ───────
api.yourdomain.com  ──A record──►  3.110.45.67 (Elastic IP)
                                        │
                                        ▼
                                   Nginx + Laravel
                                   https://api.yourdomain.com/api
```

**You do NOT need:**

- Route 53 (unless you want to move DNS to AWS later)
- To change nameservers on Hostinger
- RDS or any extra AWS DNS service

---

## 16. Configure MetaTrader 5 EA

### 16.1 Install EA

Copy `mt5/AI_Trading_EA.mq5` to MT5 `MQL5/Experts/` and compile in MetaEditor.

### 16.2 Allow WebRequest

MT5 → **Tools → Options → Expert Advisors**

- Allow algorithmic trading
- Allow WebRequest for listed URL:

```
https://api.yourdomain.com
```

### 16.3 EA inputs

| Input                | Value                             |
| -------------------- | --------------------------------- |
| `InpApiBaseUrl`      | `https://api.yourdomain.com/api`  |
| `InpApiToken`        | Same as `MT5_API_TOKEN` in `.env` |
| `InpPollIntervalSec` | `7`                               |
| `InpMinConfidence`   | `80`                              |
| `InpSymbols`         | `XAUUSD,EURUSD,GBPUSD`            |

Test on a **demo account** first.

---

## 17. Test Deployment

### Health check

```bash
curl https://api.yourdomain.com/up
```

### Market data

```bash
curl -X POST https://api.yourdomain.com/api/market-data \
  -H "Content-Type: application/json" \
  -H "X-API-TOKEN: YOUR_TOKEN" \
  -d '{
    "account": {"login": 123456, "balance": 10000, "equity": 10000, "free_margin": 9500},
    "symbols": [{
      "symbol": "XAUUSD",
      "timeframe": "M15",
      "indicators": {"ema20": 3350, "ema50": 3340, "ema200": 3300, "rsi": 64, "atr": 12},
      "candles": [{"time": "2026-06-24 10:00", "open": 3350, "high": 3360, "low": 3345, "close": 3358, "volume": 1000}]
    }]
  }'
```

Expected: `{"status":"accepted"}`

### Signals (after queue + AI processes)

```bash
curl "https://api.yourdomain.com/api/signals?account=123456" \
  -H "X-API-TOKEN: YOUR_TOKEN"
```

### Check logs

```bash
tail -f /var/www/mt5_ai/backend/storage/logs/worker.log
tail -f /var/www/mt5_ai/backend/storage/logs/laravel.log
```

### Check database

```bash
php artisan tinker
>>> \App\Models\Account::count();
>>> \App\Models\Signal::latest()->first();
```

Or directly in MySQL:

```bash
mysql -u mt5user -p mt5_ai -e "SELECT COUNT(*) FROM signals;"
```

---

## 18. Database Backups

Since MySQL is on EC2 (not RDS), set up manual backups.

### Daily backup script

```bash
sudo nano /usr/local/bin/backup-mt5-db.sh
```

Paste:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/mt5_ai"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR
mysqldump -u mt5user -p'R@vi84184' mt5_ai | gzip > "$BACKUP_DIR/mt5_ai_$DATE.sql.gz"
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
```

```bash
sudo chmod +x /usr/local/bin/backup-mt5-db.sh
```

### Cron (daily at 2 AM)

```bash
sudo crontab -e
```

Add:

```
0 2 * * * /usr/local/bin/backup-mt5-db.sh
```

### Optional: upload to S3

Install AWS CLI on EC2 and add to the backup script:

```bash
aws s3 cp "$BACKUP_DIR/mt5_ai_$DATE.sql.gz" s3://your-bucket/backups/
```

---

## 19. CI/CD with GitHub Actions (Optional)

```yaml
# .github/workflows/deploy-aws.yml
name: Deploy to AWS

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.EC2_HOST }}
          username: ubuntu
          key: ${{ secrets.EC2_SSH_KEY }}
          script: |
            cd /var/www/mt5_ai
            git pull origin main
            cd backend
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan queue:restart
            sudo supervisorctl restart mt5-ai-worker:*
```

---

## 20. Monitoring & Logs

### Application logs

```bash
tail -f /var/www/mt5_ai/backend/storage/logs/laravel.log
tail -f /var/www/mt5_ai/backend/storage/logs/worker.log
```

### Nginx logs

```bash
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
```

### MySQL status

```bash
sudo systemctl status mysql
df -h                    # disk space
free -h                  # memory
```

### Useful CloudWatch alarms (optional)

- EC2 CPU > 80%
- EC2 disk usage > 80%

---

## 21. Maintenance Commands

```bash
cd /var/www/mt5_ai/backend

# After .env changes
php artisan config:clear && php artisan config:cache

# Deploy updates
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan queue:restart
sudo supervisorctl restart mt5-ai-worker:*

# Failed jobs
php artisan queue:failed
php artisan queue:retry all

# Maintenance mode
php artisan down --secret="my-secret-token"
php artisan up

# System updates
sudo apt update && sudo apt upgrade -y
sudo systemctl restart mysql php8.5-fpm nginx   # use your PHP version
```

---

## 22. Cost Estimate

Approximate monthly (us-east-1, on-demand):

| Service       | Spec                         | ~Cost/month       |
| ------------- | ---------------------------- | ----------------- |
| EC2           | t3.small                     | $15–18            |
| Elastic IP    | attached to running instance | $0                |
| EBS storage   | 30 GB gp3                    | $2–3              |
| Route 53      | 1 hosted zone (optional)     | $0.50             |
| Data transfer | low API traffic              | $1–5              |
| **Total**     |                              | **~$18–27/month** |

No RDS cost — saves ~$12–15/month vs the RDS setup.

Free tier (first 12 months) may cover `t3.micro` EC2 if eligible.

---

## 23. Troubleshooting

### Cannot install php8.x-fpm (package not found)

Check your PHP version first: `php -v`

- **Ubuntu 22.04** — add the ondrej PPA (section 8.2), then install matching packages
- **PHP 8.5** — use `php8.5-fpm`, not `php8.3-fpm`
- **Auto-detect:**

```bash
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sudo apt install -y nginx php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-mysql \
  php${PHP_VER}-mbstring php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip \
  php${PHP_VER}-bcmath php${PHP_VER}-intl php${PHP_VER}-gd git unzip supervisor
```

### 502 Bad Gateway

```bash
sudo systemctl status php8.5-fpm nginx   # use your PHP version
sudo tail -f /var/log/nginx/error.log
sudo systemctl restart php8.5-fpm nginx
```

### Cannot connect to MySQL

```bash
sudo systemctl status mysql
sudo mysql -u mt5user -p mt5_ai
```

Check `.env`:

- `DB_HOST=127.0.0.1`
- Correct `DB_USERNAME` and `DB_PASSWORD`

Reset password if needed:

```sql
sudo mysql -u root -p
ALTER USER 'mt5user'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

### Access denied for user

User must be `'mt5user'@'localhost'` — not `@'%'`.

### Queue not processing

```bash
sudo supervisorctl status
sudo supervisorctl restart mt5-ai-worker:*
php artisan queue:failed
```

### 401 Unauthorized

- `X-API-TOKEN` must match `MT5_API_TOKEN`
- Run `php artisan config:clear` after `.env` changes

### MT5 WebRequest error 4014

- Add `https://api.yourdomain.com` to MT5 allowed URLs
- Must use HTTPS

### Disk full

```bash
df -h
sudo du -sh /var/lib/mysql/*
```

Purge old logs and backups, or increase EBS volume in EC2 console.

### Permission denied on storage

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 24. Security Checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Strong `MT5_API_TOKEN` and MySQL password
- [ ] SSH restricted to your IP only
- [ ] MySQL port 3306 **not** open in security group
- [ ] MySQL `bind-address = 127.0.0.1`
- [ ] HTTPS enabled (Certbot)
- [ ] `.env` not in Git
- [ ] Daily database backups configured
- [ ] EC2 security updates enabled
- [ ] Test on demo MT5 account before live trading

---

## 25. Alternative: AWS Lightsail

Same single-server approach on Lightsail:

| Step | Action                                                     |
| ---- | ---------------------------------------------------------- |
| 1    | Lightsail → Create instance → Ubuntu 24.04 ($10–12/mo)     |
| 2    | Attach static IP                                           |
| 3    | Follow sections 8–17 above (install MySQL on the instance) |
| 4    | Use Lightsail DNS or Route 53 for `api.yourdomain.com`     |

No separate database service — MySQL runs on the same instance.

---

## Quick Reference

| Item         | Example                                     |
| ------------ | ------------------------------------------- |
| API URL      | `https://api.yourdomain.com/api`            |
| Health       | `https://api.yourdomain.com/up`             |
| App path     | `/var/www/mt5_ai/backend`                   |
| MySQL host   | `127.0.0.1`                                 |
| Database     | `mt5_ai`                                    |
| Nginx config | `/etc/nginx/sites-available/mt5-ai`         |
| Supervisor   | `/etc/supervisor/conf.d/mt5-ai-worker.conf` |
| Backups      | `/var/backups/mt5_ai/`                      |
| MT5 header   | `X-API-TOKEN: your-secret`                  |

---

## Server Folder Structure (Final)

```
/var/www/mt5_ai/
├── backend/
│   ├── app/
│   ├── public/          ← Nginx root
│   ├── storage/logs/
│   ├── vendor/
│   ├── .env
│   └── artisan
└── mt5/
    └── AI_Trading_EA.mq5   ← deploy to MT5 terminal, not EC2

/var/lib/mysql/             ← MySQL data (system)
/var/backups/mt5_ai/        ← daily SQL dumps
```

---

## AWS vs Hostinger

| Feature      | AWS EC2 (this guide)     | Hostinger shared     |
| ------------ | ------------------------ | -------------------- |
| Queue worker | Supervisor (real-time)   | Cron every minute    |
| Database     | MySQL on same EC2        | Shared MySQL         |
| Cost         | ~$18–27/mo               | ~$5–15/mo            |
| Backups      | Manual (mysqldump + S3)  | hPanel automatic     |
| Best for     | Production / low latency | Budget / low traffic |

See also: [DEPLOYMENT_HOSTINGER.md](DEPLOYMENT_HOSTINGER.md)
