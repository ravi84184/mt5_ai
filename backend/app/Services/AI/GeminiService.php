<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService implements AiServiceInterface
{
    public function analyzeEntry(array $context): array
    {
        return $this->chat(
            PromptBuilder::entrySystemPrompt()."\n\n".PromptBuilder::entryUserPrompt($context),
        );
    }

    public function analyzePosition(array $context): array
    {
        return $this->chat(
            PromptBuilder::positionSystemPrompt()."\n\n".PromptBuilder::positionUserPrompt($context),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chat(string $prompt): array
    {
        $apiKey = config('trading.ai.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = config('trading.ai.gemini.model');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(120)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }
}
