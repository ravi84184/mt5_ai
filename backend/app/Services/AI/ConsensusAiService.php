<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConsensusAiService implements AiServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function analyzeEntry(array $context): array
    {
        return $this->mergeEntryDecisions(
            $this->collectDecisions('analyzeEntry', $context),
            $context
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function analyzePosition(array $context): array
    {
        return $this->mergePositionDecisions($this->collectDecisions('analyzePosition', $context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{provider: string, decision: array<string, mixed>}>
     */
    private function collectDecisions(string $method, array $context): array
    {
        $providers = $this->availableProviders();
        if ($providers === []) {
            throw new RuntimeException('AI consensus enabled but no API keys are configured.');
        }

        $decisions = [];
        foreach ($providers as $provider) {
            try {
                $decision = AiServiceFactory::makeConfigured($provider)->{$method}($context);
                $decisions[] = ['provider' => $provider, 'decision' => $decision];
            } catch (\Throwable $e) {
                Log::warning("Consensus: {$provider} failed", ['error' => $e->getMessage()]);
            }
        }

        if ($decisions === []) {
            throw new RuntimeException('All AI providers failed during consensus analysis.');
        }

        return $decisions;
    }

    /**
     * @return list<string>
     */
    private function availableProviders(): array
    {
        $configured = config('trading.ai.consensus.providers', ['openai', 'anthropic', 'gemini']);
        $available = [];

        foreach ($configured as $provider) {
            try {
                AiProviderConfig::ensureConfigured($provider);
                $available[] = AiProviderConfig::normalize($provider);
            } catch (\Throwable) {
                continue;
            }
        }

        return $available;
    }

    /**
     * @param  list<array{provider: string, decision: array<string, mixed>}>  $decisions
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mergeEntryDecisions(array $decisions, array $context): array
    {
        $minAgree = (int) config('trading.ai.consensus.min_agree', 2);
        $symbol = (string) ($context['symbol']['symbol'] ?? $context['symbol'] ?? 'UNKNOWN');

        $votes = ['BUY' => [], 'SELL' => [], 'WAIT' => []];
        foreach ($decisions as $item) {
            $action = strtoupper((string) ($item['decision']['action'] ?? 'WAIT'));
            if (! isset($votes[$action])) {
                $action = 'WAIT';
            }
            $votes[$action][] = $item;
        }

        $winningAction = 'WAIT';
        $winningVotes = $votes['WAIT'];

        foreach (['BUY', 'SELL'] as $action) {
            if (count($votes[$action]) >= $minAgree) {
                $winningAction = $action;
                $winningVotes = $votes[$action];
                break;
            }
        }

        if ($winningAction === 'WAIT') {
            return [
                'symbol' => $symbol,
                'action' => 'WAIT',
                'confidence' => 0,
                'reason' => 'Consensus WAIT',
            ];
        }

        $best = collect($winningVotes)->sortByDesc(
            fn ($item) => (int) ($item['decision']['confidence'] ?? 0)
        )->first();

        $decision = $best['decision'];
        $decision['action'] = $winningAction;
        $decision['confidence'] = (int) round(collect($winningVotes)->avg(
            fn ($item) => (int) ($item['decision']['confidence'] ?? 0)
        ));
        $decision['symbol'] = $decision['symbol'] ?? $symbol;
        $decision['reason'] = sprintf(
            'Consensus %s (%d/%d): %s',
            $winningAction,
            count($winningVotes),
            count($decisions),
            $decision['reason'] ?? ''
        );

        return $decision;
    }

    /**
     * @param  list<array{provider: string, decision: array<string, mixed>}>  $decisions
     * @return array<string, mixed>
     */
    private function mergePositionDecisions(array $decisions): array
    {
        $actions = [];
        foreach ($decisions as $item) {
            $action = strtoupper((string) ($item['decision']['action'] ?? 'HOLD'));
            $actions[$action] = ($actions[$action] ?? 0) + 1;
        }

        arsort($actions);
        $winningAction = array_key_first($actions) ?? 'HOLD';

        $match = collect($decisions)->first(
            fn ($item) => strtoupper((string) ($item['decision']['action'] ?? '')) === $winningAction
        );

        $decision = $match['decision'] ?? ['action' => 'HOLD', 'reason' => 'Consensus default'];
        $decision['action'] = $winningAction;

        return $decision;
    }
}
