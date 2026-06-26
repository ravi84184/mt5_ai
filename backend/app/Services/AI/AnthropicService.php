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
        );
    }

    public function analyzePosition(array $context): array
    {
        return $this->chat(
            PromptBuilder::positionSystemPrompt(),
            PromptBuilder::positionUserPrompt($context),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chat(string $system, string $user): array
    {
        $apiKey = config('trading.ai.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('trading.ai.anthropic.model'),
                'max_tokens' => 1024,
                'temperature' => 0,
                'system' => $system."\n\nRespond with a single raw JSON object only. No markdown, no code fences, no prose.",
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Anthropic API error: '.$response->json('error.message', $response->body())
            );
        }

        $blocks = $response->json('content', []);
        $text = collect($blocks)
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return $this->parseJsonResponse($text, 'Anthropic');
    }
}
