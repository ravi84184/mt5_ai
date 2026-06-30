<?php

namespace App\Console\Commands;

use App\Jobs\RunBacktestJob;
use App\Models\Account;
use App\Models\BacktestRun;
use Illuminate\Console\Command;

class BacktestCommand extends Command
{
    protected $signature = 'trading:backtest
        {symbol : Symbol e.g. XAUUSD}
        {--account= : MT5 login}
        {--from= : Start date}
        {--to= : End date}
        {--strategy=balanced : conservative|balanced|active}
        {--sync : Run synchronously}';

    protected $description = 'Run rule-based backtest on stored snapshots';

    public function handle(): int
    {
        $accountId = null;
        if ($login = $this->option('account')) {
            $account = Account::where('mt5_login', $login)->first();
            if (! $account) {
                $this->error("Account not found: {$login}");

                return self::FAILURE;
            }
            $accountId = $account->id;
        }

        $run = BacktestRun::create([
            'account_id' => $accountId,
            'symbol' => strtoupper($this->argument('symbol')),
            'from_date' => $this->option('from') ?? now()->subDays(30)->toDateString(),
            'to_date' => $this->option('to') ?? now()->toDateString(),
            'mode' => 'rules',
            'status' => 'PENDING',
            'params_json' => ['strategy' => $this->option('strategy')],
        ]);

        if ($this->option('sync')) {
            $engine = app(\App\Services\Backtesting\BacktestEngine::class);
            $engine->run($run);
            $r = $run->fresh()->results_json ?? [];
            $this->table(['Metric', 'Value'], [
                ['Status', $run->fresh()->status],
                ['Trades', $r['total_trades'] ?? 0],
                ['Win rate', ($r['win_rate'] ?? 0).'%'],
                ['Total R', $r['total_r'] ?? 0],
            ]);
        } else {
            RunBacktestJob::dispatch($run->id);
            $this->info("Backtest #{$run->id} queued.");
        }

        return self::SUCCESS;
    }
}
