<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicService implements AiServiceInterface
{
    use ParsesAiJsonResponse;

    public function analyzeEntry(array $context): array
    {
        return $this->chat(
            PromptBuilder::entrySystemPrompt(),
            PromptBuilder::entryUserPrompt($context),
            AiResponseSchema::entry(),
        );
    }

    public function analyzePosition(array $context): array
    {
        return $this->chat(
            PromptBuilder::positionSystemPrompt(),
            PromptBuilder::positionUserPrompt($context),
            AiResponseSchema::position(),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function chat(string $system, string $user, array $schema): array
    {
        $apiKey = config('trading.ai.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $payload = [
            'model' => config('trading.ai.anthropic.model'),
            'max_tokens' => 1024,
            'temperature' => 0,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $schema,
                ],
            ],
        ];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if ($response->failed() && self::isStructuredOutputError($response)) {
            unset($payload['output_config']);
            $payload['system'] = $system."\n\nRespond with a single raw JSON object only. No markdown, no code fences, no prose.";

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(120)
                ->post('https://api.anthropic.com/v1/messages', $payload);
        }

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());
            $hint = str_contains((string) $error, 'model')
                ? ' Check Anthropic model ID in Super Admin → Trading settings (e.g. claude-sonnet-4-6).'
                : '';

            throw new RuntimeException('Anthropic API error: '.$error.$hint);
        }

        $text = $this->extractText($response->json('content', []));

        return $this->parseJsonResponse($text, 'Anthropic');
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function extractText(array $blocks): string
    {
        return collect($blocks)
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private static function isStructuredOutputError(\Illuminate\Http\Client\Response $response): bool
    {
        $body = strtolower($response->body());

        return str_contains($body, 'output_config')
            || str_contains($body, 'json_schema')
            || str_contains($body, 'structured');
    }
}
