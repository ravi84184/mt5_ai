<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMt5Command extends Command
{
    protected $signature = 'mt5:diagnose {--account= : MT5 login to check signals for}';

    protected $description = 'Diagnose MT5 ↔ Laravel signal pipeline';

    public function handle(): int
    {
        $this->info('MT5 AI Trading — Diagnostics');
        $this->newLine();

        $this->line('Config:');
        $this->table(['Key', 'Value'], [
            ['APP_URL', config('app.url')],
            ['APP_TIMEZONE', config('app.timezone')],
            ['QUEUE_CONNECTION', config('queue.default')],
            ['AI_PROVIDER', config('trading.ai.provider')],
            ['OPENAI_API_KEY', $this->mask(config('trading.ai.openai.api_key'))],
            ['MT5_API_TOKEN', $this->mask(config('trading.api_token'))],
        ]);

        $this->newLine();
        $this->line('Risk config (server time: '.now()->format('H:i T').'):');
        $this->table(['Key', 'Value'], [
            ['MIN_CONFIDENCE', config('trading.risk.min_confidence')],
            ['MAX_OPEN_TRADES', config('trading.risk.max_open_trades')],
            ['TRADING_SESSIONS', config('trading.risk.trading_sessions')],
            ['OPEN trades in DB', \App\Models\Trade::where('status', 'OPEN')->count()],
        ]);

        $this->newLine();
        $this->line('Database:');
        $this->table(['Table', 'Count'], [
            ['accounts', Account::count()],
            ['market_snapshots', MarketSnapshot::count()],
            ['signals (total)', Signal::count()],
            ['signals PENDING BUY/SELL', Signal::where('status', 'PENDING')->whereIn('action', ['BUY', 'SELL'])->count()],
            ['signals WAIT', Signal::where('action', 'WAIT')->count()],
            ['signals REJECTED', Signal::where('status', 'REJECTED')->count()],
            ['jobs (queued)', DB::table('jobs')->count()],
            ['failed_jobs', DB::table('failed_jobs')->count()],
        ]);

        $latestSnapshot = MarketSnapshot::latest()->first();
        if ($latestSnapshot) {
            $this->newLine();
            $this->line('Latest market snapshot:');
            $this->line("  account_id={$latestSnapshot->account_id} symbol={$latestSnapshot->symbol} at {$latestSnapshot->created_at}");
        } else {
            $this->warn('No market snapshots — MT5 has not sent data yet.');
        }

        $accounts = Account::all(['id', 'mt5_login', 'balance', 'updated_at']);
        if ($accounts->isNotEmpty()) {
            $this->newLine();
            $this->line('Accounts (MT5 logins):');
            $this->table(['id', 'mt5_login', 'balance', 'updated_at'], $accounts->toArray());
        }

        $latestSignals = Signal::latest()->take(5)->get(['id', 'account_id', 'symbol', 'action', 'confidence', 'status', 'rejection_reason', 'reason', 'created_at']);
        if ($latestSignals->isNotEmpty()) {
            $this->newLine();
            $this->line('Latest signals:');
            $this->table(
                ['id', 'symbol', 'action', 'conf', 'status', 'rejection_reason', 'created_at'],
                $latestSignals->map(fn ($s) => [
                    $s->id, $s->symbol, $s->action, $s->confidence,
                    $s->status->value ?? $s->status,
                    \Illuminate\Support\Str::limit($s->rejection_reason ?? '-', 50),
                    $s->created_at,
                ])->toArray()
            );
        }

        $accountLogin = $this->option('account');
        if ($accountLogin) {
            $this->newLine();
            $account = Account::where('mt5_login', $accountLogin)->first();
            if (! $account) {
                $this->error("No account found for MT5 login: {$accountLogin}");
            } else {
                $pending = Signal::pendingForAccount($account->id)->first();
                if ($pending) {
                    $this->info("Poll would return signal #{$pending->id}: {$pending->action} {$pending->symbol} (confidence {$pending->confidence})");
                } else {
                    $this->warn("Poll would return NO_SIGNAL for login {$accountLogin}");
                }
            }
        }

        if (DB::table('jobs')->count() > 0) {
            $this->newLine();
            $this->warn('Jobs are queued but not processed — run: sudo supervisorctl status');
        }

        if (DB::table('failed_jobs')->count() > 0) {
            $this->newLine();
            $this->error('Failed jobs exist — run: php artisan queue:failed');
        }

        $this->newLine();
        $this->line('Tip: php artisan mt5:diagnose --account=YOUR_MT5_LOGIN');

        return self::SUCCESS;
    }

    private function mask(?string $value): string
    {
        if (! $value) {
            return '(not set)';
        }

        if (strlen($value) <= 8) {
            return '****';
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }
}
