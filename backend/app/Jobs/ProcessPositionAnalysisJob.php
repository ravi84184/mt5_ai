<?php

namespace App\Jobs;

use App\Enums\TradeManagementAction;
use App\Models\Account;
use App\Models\PositionManagementDecision;
use App\Models\Trade;
use App\Models\TradeManagementLog;
use App\Services\AI\AiServiceFactory;
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

        try {
            $decision = AiServiceFactory::make()->analyzePosition([
                'ticket' => $ticket,
                'position' => $this->payload['position'] ?? [],
                'market_data' => $this->payload['market_data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('AI position analysis failed', [
                'account_id' => $account->id,
                'ticket' => $ticket,
                'error' => $e->getMessage(),
            ]);

            return;
        }

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
