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
        ?string $provider = null,
    ): AiInteractionLog {
        $provider = AiProviderConfig::normalize($provider ?? config('trading.ai.provider'));

        return AiInteractionLog::create([
            'account_id' => $accountId,
            'signal_id' => $signalId,
            'analysis_type' => $analysisType,
            'provider' => $provider,
            'model' => AiProviderConfig::model($provider),
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
        ?string $provider = null,
    ): AiInteractionLog {
        $provider = AiProviderConfig::normalize($provider ?? config('trading.ai.provider'));

        return AiInteractionLog::create([
            'account_id' => $accountId,
            'analysis_type' => $analysisType,
            'provider' => $provider,
            'model' => AiProviderConfig::model($provider),
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
}
