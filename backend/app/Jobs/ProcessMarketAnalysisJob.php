<?php

namespace App\Jobs;

use App\Enums\SignalAction;
use App\Enums\SignalStatus;
use App\Models\Account;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Services\AI\AiServiceFactory;
use App\Services\RiskManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMarketAnalysisJob implements ShouldQueue
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

    public function handle(RiskManagementService $riskService): void
    {
        $account = Account::findOrFail($this->accountId);
        $ai = AiServiceFactory::make();
        $provider = config('trading.ai.provider');

        foreach ($this->payload['symbols'] ?? [] as $symbolData) {
            $symbol = $symbolData['symbol'] ?? null;
            if (! $symbol) {
                continue;
            }

            MarketSnapshot::create([
                'account_id' => $account->id,
                'symbol' => $symbol,
                'timeframe' => $symbolData['timeframe'] ?? 'M15',
                'snapshot_json' => $symbolData,
            ]);

            $hasOpenTrade = $account->trades()
                ->where('symbol', $symbol)
                ->where('status', 'OPEN')
                ->exists();

            if ($hasOpenTrade) {
                continue;
            }

            try {
                $context = [
                    'account' => [
                        'balance' => $account->balance,
                        'equity' => $account->equity,
                    ],
                    'symbol' => $symbolData,
                    'risk' => config('trading.risk'),
                ];

                $decision = $ai->analyzeEntry($context);
            } catch (\Throwable $e) {
                Log::error('AI entry analysis failed', [
                    'account_id' => $account->id,
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $action = SignalAction::tryFromMixed($decision['action'] ?? 'WAIT') ?? SignalAction::Wait;

            $signal = Signal::create([
                'account_id' => $account->id,
                'symbol' => $decision['symbol'] ?? $symbol,
                'action' => $action->value,
                'entry_price' => $decision['entry_price'] ?? null,
                'stop_loss' => $decision['stop_loss'] ?? null,
                'take_profit' => $decision['take_profit'] ?? null,
                'confidence' => (int) ($decision['confidence'] ?? 0),
                'reason' => $decision['reason'] ?? null,
                'status' => SignalStatus::Pending,
                'ai_provider' => $provider,
            ]);

            if ($rejection = $riskService->getRejectionReason($account, $signal)) {
                $signal->update([
                    'status' => SignalStatus::Rejected,
                    'rejection_reason' => $rejection,
                ]);
            }
        }
    }
}
