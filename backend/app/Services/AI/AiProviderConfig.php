<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use InvalidArgumentException;

class AiProviderConfig
{
    public static function normalize(?string $provider): string
    {
        return AiProvider::tryFromMixed($provider)?->value
            ?? strtolower($provider ?? config('trading.ai.provider', 'openai'));
    }

    public static function isConfigured(?string $provider = null): bool
    {
        $provider = self::normalize($provider);

        return match ($provider) {
            'openai' => filled(config('trading.ai.openai.api_key')),
            'anthropic' => filled(config('trading.ai.anthropic.api_key')),
            'gemini' => filled(config('trading.ai.gemini.api_key')),
            default => false,
        };
    }

    public static function model(?string $provider = null): ?string
    {
        $provider = self::normalize($provider);

        return match ($provider) {
            'openai' => config('trading.ai.openai.model'),
            'anthropic' => config('trading.ai.anthropic.model'),
            'gemini' => config('trading.ai.gemini.model'),
            default => null,
        };
    }

    public static function apiKey(?string $provider = null): ?string
    {
        $provider = self::normalize($provider);

        return match ($provider) {
            'openai' => config('trading.ai.openai.api_key'),
            'anthropic' => config('trading.ai.anthropic.api_key'),
            'gemini' => config('trading.ai.gemini.api_key'),
            default => null,
        };
    }

    public static function ensureConfigured(?string $provider = null): void
    {
        $provider = self::normalize($provider);

        if (self::isConfigured($provider)) {
            return;
        }

        $label = AiProvider::tryFromMixed($provider)?->label() ?? $provider;

        throw new InvalidArgumentException(
            "{$label} API key is not configured. Add it in Super Admin → System → Trading settings."
        );
    }
}
