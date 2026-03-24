<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected bool $verifySsl;
    protected int $timeout;
    protected int $connectTimeout;
    protected float $temperature;

    public function __construct(private readonly GeminiPromptBuilder $promptBuilder)
    {
        $this->apiKey = (string) config('services.gemini.api_key');
        $this->model = (string) config('services.gemini.model', 'llama3.2:latest');
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'http://localhost:11434'), '/');
        $this->verifySsl = (bool) config('services.gemini.verify_ssl', false);
        $this->timeout = (int) config('services.gemini.timeout', 120);
        $this->connectTimeout = (int) config('services.gemini.connect_timeout', 30);
        $this->temperature = (float) config('services.gemini.temperature', 0.7);
    }

    /**
     * Generate a response from Gemini.
     *
     * @param string $prompt
     * @param array $history
     * @param string $context
     * @return string|null
     */
    public function generateResponse(string $prompt, array $history = [], string $context = ''): ?string
    {
        $systemInstruction = $this->promptBuilder->buildSystemInstruction($context);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemInstruction,
            ],
        ];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $this->mapRole((string) ($message['role'] ?? 'user')),
                'content' => (string) ($message['parts'][0]['text'] ?? ''),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'stream' => false,
        ];

        try {
            $response = Http::withOptions([
                'verify' => $this->verifySsl,
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/api/chat/completions', $payload);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                if (is_string($content)) {
                    return $content;
                }

                if (is_array($content)) {
                    return collect($content)
                        ->map(fn ($item) => $item['text'] ?? '')
                        ->filter()
                        ->implode("\n");
                }

                return null;
            }

            Log::error('Ollama API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $errorMsg = $this->extractErrorMessage($response->json(), $response->body());
            return "Erro na API: " . $errorMsg;

        } catch (\Exception $e) {
            Log::error('Ollama Service Exception: ' . $e->getMessage());
            return "Erro na comunicação: " . $e->getMessage();
        }
    }

    protected function mapRole(string $role): string
    {
        return match ($role) {
            'model' => 'assistant',
            'assistant', 'system', 'user' => $role,
            default => 'user',
        };
    }

    protected function extractErrorMessage(mixed $jsonBody, string $rawBody): string
    {
        if (is_array($jsonBody)) {
            $candidates = [
                data_get($jsonBody, 'error.message'),
                data_get($jsonBody, 'error'),
                data_get($jsonBody, 'message'),
                data_get($jsonBody, 'detail'),
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    return $candidate;
                }
            }
        }

        $rawBody = trim($rawBody);

        return $rawBody !== '' ? $rawBody : 'Erro desconhecido na API do Ollama.';
    }
}
