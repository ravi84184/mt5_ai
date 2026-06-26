<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService implements AiServiceInterface
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
        $apiKey = config('trading.ai.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $model = config('trading.ai.gemini.model');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent';

        $response = Http::timeout(120)
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $user]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini API error: '.$response->json('error.message', $response->body())
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            $blockReason = $response->json('candidates.0.finishReason');
            throw new RuntimeException(
                'Gemini returned an empty response'.($blockReason ? " ({$blockReason})" : '.')
            );
        }

        return $this->parseJsonResponse($text, 'Gemini');
    }
}
