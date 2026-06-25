<?php

namespace App\Services\AI;

use InvalidArgumentException;

class AiServiceFactory
{
    public static function make(?string $provider = null): AiServiceInterface
    {
        $provider = strtolower($provider ?? config('trading.ai.provider', 'openai'));

        return match ($provider) {
            'openai', 'gpt' => app(OpenAiService::class),
            'anthropic', 'claude' => app(AnthropicService::class),
            'gemini', 'google' => app(GeminiService::class),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }
}
