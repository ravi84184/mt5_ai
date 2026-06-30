<?php

namespace App\Services\AI;

use InvalidArgumentException;

class AiServiceFactory
{
    public static function make(?string $provider = null, bool $useConsensus = true): AiServiceInterface
    {
        if ($useConsensus && config('trading.ai.consensus.enabled', false)) {
            return app(ConsensusAiService::class);
        }

        $provider = AiProviderConfig::normalize($provider);

        return match ($provider) {
            'openai' => app(OpenAiService::class),
            'anthropic' => app(AnthropicService::class),
            'gemini' => app(GeminiService::class),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }

    public static function makeConfigured(?string $provider = null, bool $useConsensus = true): AiServiceInterface
    {
        if ($useConsensus && config('trading.ai.consensus.enabled', false)) {
            return app(ConsensusAiService::class);
        }

        $provider = AiProviderConfig::normalize($provider);
        AiProviderConfig::ensureConfigured($provider);

        return self::make($provider, false);
    }
}
