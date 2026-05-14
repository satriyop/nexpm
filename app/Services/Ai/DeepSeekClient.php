<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient
{
    /**
     * @param  array<string, mixed>  $toolData
     * @return array{content: string, usage: array<string, mixed>, model: string}
     *
     * @throws RequestException
     */
    public function complete(string $systemPrompt, string $userPrompt, array $toolData): array
    {
        $apiKey = config('services.deepseek.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('DeepSeek API key is not configured.');
        }

        $baseUrl = rtrim((string) config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');
        $model = (string) config('services.deepseek.model', 'deepseek-chat');

        $response = Http::withToken($apiKey)
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.deepseek.timeout', 20))
            ->connectTimeout(5)
            ->post('/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    [
                        'role' => 'user',
                        'content' => $userPrompt."\n\nApplication data:\n".json_encode($toolData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'temperature' => 0.2,
            ])
            ->throw();

        return [
            'content' => trim((string) data_get($response->json(), 'choices.0.message.content')),
            'usage' => data_get($response->json(), 'usage', []),
            'model' => (string) data_get($response->json(), 'model', $model),
        ];
    }
}
