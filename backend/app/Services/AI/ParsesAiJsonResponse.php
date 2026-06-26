<?php

namespace App\Services\AI;

use RuntimeException;

trait ParsesAiJsonResponse
{
    /**
     * @return array<string, mixed>
     */
    protected function parseJsonResponse(string $text, string $providerLabel): array
    {
        return AiJsonParser::parse($text, $providerLabel);
    }
}
