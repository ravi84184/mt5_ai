<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiService implements AiServiceInterface
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
        $apiKey = config('trading.ai.openai.api_key');
        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('trading.ai.openai.model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.2,
            ]);

        $response->throw();

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
