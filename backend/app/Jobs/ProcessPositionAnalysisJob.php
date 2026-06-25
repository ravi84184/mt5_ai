<?php

namespace App\Jobs;

use App\Enums\TradeManagementAction;
use App\Models\Account;
use App\Models\PositionManagementDecision;
use App\Models\Trade;
use App\Models\TradeManagementLog;
use App\Services\AI\AiInteractionLogger;
use App\Services\AI\AiServiceFactory;
use App\Services\AI\PromptBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPositionAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $accountId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $account = Account::findOrFail($this->accountId);
        $ticket = (int) ($this->payload['ticket'] ?? 0);

        if ($ticket <= 0) {
            return;
        }

        $trade = Trade::where('ticket', $ticket)
            ->where('account_id', $account->id)
            ->where('status', 'OPEN')
            ->first();

        if (! $trade) {
            return;
        }

        $context = [
            'ticket' => $ticket,
            'position' => $this->payload['position'] ?? [],
            'market_data' => $this->payload['market_data'] ?? [],
        ];

        $systemPrompt = PromptBuilder::positionSystemPrompt();
        $userPrompt = PromptBuilder::positionUserPrompt($context);
        $symbol = $context['position']['symbol'] ?? null;
        $startedAt = microtime(true);

        try {
            $decision = AiServiceFactory::make()->analyzePosition($context);
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            AiInteractionLogger::logError(
                'position',
                $context,
                $systemPrompt,
                $userPrompt,
                $e->getMessage(),
                $durationMs,
                $account->id,
                $symbol,
                $ticket,
            );
            Log::error('AI position analysis failed', [
                'account_id' => $account->id,
                'ticket' => $ticket,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        AiInteractionLogger::logSuccess(
            'position',
            $context,
            $systemPrompt,
            $userPrompt,
            $decision,
            $durationMs,
            $account->id,
            null,
            $symbol,
            $ticket,
        );

        $action = TradeManagementAction::tryFromMixed($decision['action'] ?? 'HOLD')
            ?? TradeManagementAction::Hold;

        TradeManagementLog::create([
            'ticket' => $ticket,
            'account_id' => $account->id,
            'action' => $action->value,
            'old_sl' => $this->payload['position']['sl'] ?? null,
            'new_sl' => $decision['new_sl'] ?? null,
            'close_volume' => $decision['close_volume'] ?? null,
            'reason' => $decision['reason'] ?? null,
            'status' => $action === TradeManagementAction::Hold ? 'APPLIED' : 'PENDING',
        ]);

        if ($action === TradeManagementAction::Hold) {
            return;
        }

        PositionManagementDecision::create([
            'ticket' => $ticket,
            'account_id' => $account->id,
            'action' => $action->value,
            'new_sl' => $decision['new_sl'] ?? null,
            'close_volume' => $decision['close_volume'] ?? null,
            'reason' => $decision['reason'] ?? null,
            'status' => 'PENDING',
        ]);
    }
}
