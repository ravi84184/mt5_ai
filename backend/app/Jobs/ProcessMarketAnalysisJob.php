<?php

namespace App\Jobs;

use App\Enums\SignalAction;
use App\Enums\SignalStatus;
use App\Models\Account;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Services\AI\AiInteractionLogger;
use App\Services\AI\AiServiceFactory;
use App\Services\AI\PromptBuilder;
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

        if (! $account->isTradingEnabled()) {
            return;
        }

        if (! $account->hasSymbolRestrictions()) {
            Log::info('Skipping AI analysis — no symbols configured for account', [
                'account_id' => $account->id,
                'mt5_login' => $account->mt5_login,
            ]);

            return;
        }

        $provider = $account->resolvedAiProvider();
        $ai = AiServiceFactory::make($provider);

        foreach ($this->payload['symbols'] ?? [] as $symbolData) {
            $symbol = $symbolData['symbol'] ?? null;
            if (! $symbol || ! $account->isSymbolAllowed($symbol)) {
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

            $context = [
                'account' => [
                    'balance' => $account->balance,
                    'equity' => $account->equity,
                ],
                'symbol' => $symbolData,
                'risk' => array_merge(config('trading.risk'), [
                    'min_confidence' => $account->resolvedMinConfidence(),
                    'max_open_trades' => $account->resolvedMaxOpenTrades(),
                ]),
            ];

            $systemPrompt = PromptBuilder::entrySystemPrompt();
            $userPrompt = PromptBuilder::entryUserPrompt($context);
            $startedAt = microtime(true);

            try {
                $decision = $ai->analyzeEntry($context);
                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            } catch (\Throwable $e) {
                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                AiInteractionLogger::logError(
                    'entry',
                    $context,
                    $systemPrompt,
                    $userPrompt,
                    $e->getMessage(),
                    $durationMs,
                    $account->id,
                    $symbol,
                );
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

            AiInteractionLogger::logSuccess(
                'entry',
                $context,
                $systemPrompt,
                $userPrompt,
                $decision,
                $durationMs,
                $account->id,
                $signal->id,
                $signal->symbol,
            );

            if ($rejection = $riskService->getRejectionReason($account, $signal)) {
                $signal->update([
                    'status' => SignalStatus::Rejected,
                    'rejection_reason' => $rejection,
                ]);
            }
        }
    }
}
