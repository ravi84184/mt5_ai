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
                    return self::normalizeDecoded($decoded);
                }
            } catch (JsonException $e) {
                $lastError = $e;
            }
        }

        $message = $lastError?->getMessage() ?? 'unknown parse error';
        $preview = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text) ?? $text, 300);

        throw new RuntimeException(
            "{$providerLabel} returned invalid JSON: {$message}. Preview: {$preview}"
        );
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $text): array
    {
        $text = trim(self::normalizeText($text));
        $candidates = [];

        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $candidates[] = trim($block);
            }
        }

        if ($balanced = self::extractBalancedJsonObject($text)) {
            $candidates[] = $balanced;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }

        $candidates[] = $text;

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

    private static function normalizeText(string $text): string
    {
        $text = ltrim($text, "\xEF\xBB\xBF");
        $text = str_replace(["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"], ['"', '"', "'", "'"], $text);

        return trim($text);
    }

    private static function extractBalancedJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private static function repairCommonIssues(string $json): string
    {
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        $json = preg_replace('/^\s*json\s*/i', '', $json) ?? $json;

        return trim($json);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private static function normalizeDecoded(array $decoded): array
    {
        if (isset($decoded['action']) && is_string($decoded['action'])) {
            $decoded['action'] = strtoupper(trim($decoded['action']));
        }

        if (isset($decoded['new_sl']) && $decoded['new_sl'] === 0) {
            $decoded['new_sl'] = null;
        }

        if (isset($decoded['close_volume']) && $decoded['close_volume'] === 0) {
            $decoded['close_volume'] = null;
        }

        return $decoded;
    }
}
