<?php

namespace App\Console\Commands;

use App\Models\AiInteractionLog;
use Illuminate\Console\Command;

class AiLogsCommand extends Command
{
    protected $signature = 'ai:logs
                            {--limit=10 : Number of logs to show}
                            {--id= : Show full detail for one log ID}
                            {--symbol= : Filter by symbol}
                            {--type= : Filter by analysis type (entry|position)}';

    protected $description = 'View AI input/output interaction logs';

    public function handle(): int
    {
        if ($id = $this->option('id')) {
            return $this->showDetail((int) $id);
        }

        $query = AiInteractionLog::query()->latest();

        if ($symbol = $this->option('symbol')) {
            $query->where('symbol', $symbol);
        }

        if ($type = $this->option('type')) {
            $query->where('analysis_type', $type);
        }

        $logs = $query->limit((int) $this->option('limit'))->get();

        if ($logs->isEmpty()) {
            $this->warn('No AI interaction logs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'type', 'symbol', 'provider', 'status', 'ms', 'output action', 'created_at'],
            $logs->map(fn (AiInteractionLog $log) => [
                $log->id,
                $log->analysis_type,
                $log->symbol ?? '-',
                $log->provider,
                $log->status,
                $log->duration_ms ?? '-',
                $log->output_json['action'] ?? ($log->error_message ? 'ERROR' : '-'),
                $log->created_at,
            ])->toArray()
        );

        $this->newLine();
        $this->line('View full log: php artisan ai:logs --id=<id>');

        return self::SUCCESS;
    }

    private function showDetail(int $id): int
    {
        $log = AiInteractionLog::find($id);

        if (! $log) {
            $this->error("Log #{$id} not found.");

            return self::FAILURE;
        }

        $this->info("AI Log #{$log->id} — {$log->analysis_type} — {$log->status}");
        $this->line("Provider: {$log->provider} | Model: {$log->model}");
        $this->line("Symbol: {$log->symbol} | Signal: {$log->signal_id} | Ticket: {$log->ticket}");
        $this->line("Duration: {$log->duration_ms}ms | At: {$log->created_at}");
        $this->newLine();

        $this->comment('=== INPUT (context JSON) ===');
        $this->line(json_encode($log->input_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();

        $this->comment('=== SYSTEM PROMPT ===');
        $this->line($log->system_prompt);
        $this->newLine();

        $this->comment('=== USER PROMPT ===');
        $this->line($log->user_prompt);
        $this->newLine();

        if ($log->status === 'error') {
            $this->error('=== ERROR ===');
            $this->line($log->error_message);
        } else {
            $this->comment('=== OUTPUT (AI response) ===');
            $this->line(json_encode($log->output_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
