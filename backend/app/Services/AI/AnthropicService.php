<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicService implements AiServiceInterface
{
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
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('trading.ai.anthropic.model'),
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        $response->throw();

        $blocks = $response->json('content', []);
        $text = collect($blocks)
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if ($text === '') {
            throw new RuntimeException('Anthropic returned an empty response.');
        }

        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }
}
