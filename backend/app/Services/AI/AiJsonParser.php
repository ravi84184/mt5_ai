<?php

namespace App\Services\AI;

use JsonException;
use RuntimeException;

class AiJsonParser
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(string $text, string $providerLabel): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException("{$providerLabel} returned an empty response.");
        }

        $lastError = null;

        foreach (self::candidates($text) as $candidate) {
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException $e) {
                $lastError = $e;
            }
        }

        $message = $lastError?->getMessage() ?? 'unknown parse error';
        $preview = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text) ?? $text, 200);

        throw new RuntimeException(
            "{$providerLabel} returned invalid JSON: {$message}. Preview: {$preview}"
        );
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $text): array
    {
        $text = trim($text);
        $candidates = [];

        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $candidates[] = trim($block);
            }
        }

        $candidates[] = $text;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }

        $expanded = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $expanded[] = $candidate;
            $repaired = self::repairCommonIssues($candidate);
            if ($repaired !== $candidate) {
                $expanded[] = $repaired;
            }
        }

        return array_values(array_unique($expanded));
    }

    private static function repairCommonIssues(string $json): string
    {
        // Trailing commas before } or ]
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;

        // Strip UTF-8 BOM
        return ltrim($json, "\xEF\xBB\xBF");
    }
}
