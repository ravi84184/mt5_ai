<?php

namespace App\Jobs;

use App\Enums\SignalAction;
use App\Enums\SignalStatus;
use App\Jobs\Concerns\AppliesTradingSettings;
use App\Models\Account;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Services\AI\AiContextBuilder;
use App\Services\AI\AiInteractionLogger;
use App\Services\AI\AiServiceFactory;
use App\Services\AI\MarketContextEnricher;
use App\Services\AI\PromptBuilder;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\PreTradeFilterService;
use App\Services\RiskManagementService;
use App\Services\SignalValidatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMarketAnalysisJob implements ShouldQueue
{
    use AppliesTradingSettings;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $accountId,
        public array $payload,
    ) {}

    public function handle(
        RiskManagementService $riskService,
        PreTradeFilterService $preFilter,
        SignalValidatorService $signalValidator,
        TelegramNotificationService $telegram,
    ): void {
        $this->applyTradingSettings();

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

        $provider = config('trading.ai.consensus.enabled', false)
            ? 'consensus'
            : $account->resolvedAiProvider();

        try {
            $ai = AiServiceFactory::makeConfigured($provider);
        } catch (\InvalidArgumentException $e) {
            Log::error('AI provider not configured', [
                'account_id' => $account->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return;
        }

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

            if ($account->trades()->where('symbol', $symbol)->where('status', 'OPEN')->exists()) {
                continue;
            }

            $enriched = MarketContextEnricher::enrich($symbolData);
            $context = AiContextBuilder::buildEntryContext($account, $symbolData, $enriched, $symbol);

            if ($skipReason = $preFilter->getSkipReason($symbolData, $enriched)) {
                Signal::create([
                    'account_id' => $account->id,
                    'symbol' => $symbol,
                    'action' => SignalAction::Wait->value,
                    'confidence' => 0,
                    'reason' => $skipReason,
                    'status' => SignalStatus::Rejected,
                    'rejection_reason' => "Pre-filter: {$skipReason}",
                    'ai_provider' => 'prefilter',
                ]);
                continue;
            }

            $systemPrompt = PromptBuilder::entrySystemPrompt();
            $userPrompt = PromptBuilder::entryUserPrompt($context);
            $startedAt = microtime(true);

            try {
                $decision = $ai->analyzeEntry($context);
                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            } catch (\Throwable $e) {
                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                AiInteractionLogger::logError(
                    'entry', $context, $systemPrompt, $userPrompt, $e->getMessage(),
                    $durationMs, $account->id, $symbol, null, $provider,
                );
                Log::error('AI entry analysis failed', ['account_id' => $account->id, 'symbol' => $symbol, 'error' => $e->getMessage()]);
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
                'entry', $context, $systemPrompt, $userPrompt, $decision,
                $durationMs, $account->id, $signal->id, $signal->symbol, null, $provider,
            );

            if ($action === SignalAction::Wait) {
                $signal->update(['status' => SignalStatus::Rejected, 'rejection_reason' => 'AI returned WAIT']);
                $telegram->notifySignalRejected($signal->fresh(), $account);
                continue;
            }

            if ($validationReason = $signalValidator->getRejectionReason($signal, $symbolData)) {
                $signal->update(['status' => SignalStatus::Rejected, 'rejection_reason' => "Signal validator: {$validationReason}"]);
                $telegram->notifySignalRejected($signal->fresh(), $account);
                continue;
            }

            if ($rejection = $riskService->getRejectionReason($account, $signal)) {
                $signal->update(['status' => SignalStatus::Rejected, 'rejection_reason' => $rejection]);
                $telegram->notifySignalRejected($signal->fresh(), $account);
            } else {
                $telegram->notifySignalReady($signal->fresh(), $account);
            }
        }
    }
}
