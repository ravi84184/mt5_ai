<?php

namespace App\Services\AI;

use App\Models\AiInteractionLog;

class AiInteractionLogger
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $output
     */
    public static function logSuccess(
        string $analysisType,
        array $context,
        string $systemPrompt,
        string $userPrompt,
        array $output,
        int $durationMs,
        ?int $accountId = null,
        ?int $signalId = null,
        ?string $symbol = null,
        ?int $ticket = null,
    ): AiInteractionLog {
        return AiInteractionLog::create([
            'account_id' => $accountId,
            'signal_id' => $signalId,
            'analysis_type' => $analysisType,
            'provider' => config('trading.ai.provider'),
            'model' => self::resolveModel(),
            'symbol' => $symbol,
            'ticket' => $ticket,
            'input_json' => $context,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'output_json' => $output,
            'status' => 'success',
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logError(
        string $analysisType,
        array $context,
        string $systemPrompt,
        string $userPrompt,
        string $errorMessage,
        int $durationMs,
        ?int $accountId = null,
        ?string $symbol = null,
        ?int $ticket = null,
    ): AiInteractionLog {
        return AiInteractionLog::create([
            'account_id' => $accountId,
            'analysis_type' => $analysisType,
            'provider' => config('trading.ai.provider'),
            'model' => self::resolveModel(),
            'symbol' => $symbol,
            'ticket' => $ticket,
            'input_json' => $context,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'status' => 'error',
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
        ]);
    }

    private static function resolveModel(): ?string
    {
        $provider = config('trading.ai.provider');

        return match ($provider) {
            'openai', 'gpt' => config('trading.ai.openai.model'),
            'anthropic', 'claude' => config('trading.ai.anthropic.model'),
            'gemini', 'google' => config('trading.ai.gemini.model'),
            default => null,
        };
    }
}
