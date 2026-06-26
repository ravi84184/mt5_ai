<?php

namespace App\Services\AI;

use JsonException;
use RuntimeException;

trait ParsesAiJsonResponse
{
    /**
     * @return array<string, mixed>
     */
    protected function parseJsonResponse(string $text, string $providerLabel): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException("{$providerLabel} returned an empty response.");
        }

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
            $text = trim($text);
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("{$providerLabel} returned invalid JSON: {$e->getMessage()}");
        }

        if (! is_array($decoded)) {
            throw new RuntimeException("{$providerLabel} response must be a JSON object.");
        }

        return $decoded;
    }
}
