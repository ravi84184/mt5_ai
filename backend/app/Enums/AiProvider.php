<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI (GPT)',
            self::Anthropic => 'Anthropic (Claude)',
            self::Gemini => 'Google (Gemini)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromMixed(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (strtolower($value)) {
            'openai', 'gpt' => self::OpenAi,
            'anthropic', 'claude' => self::Anthropic,
            'gemini', 'google' => self::Gemini,
            default => null,
        };
    }
}
