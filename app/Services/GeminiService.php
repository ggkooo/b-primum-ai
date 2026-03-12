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

    public function __construct(private readonly GeminiPromptBuilder $promptBuilder)
    {
        $this->apiKey = (string) config('services.gemini.api_key');
        $this->model = (string) config('services.gemini.model', 'gemini-flash-latest');
        $this->baseUrl = (string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models/');
        $this->verifySsl = (bool) config('services.gemini.verify_ssl', false);
        $this->timeout = (int) config('services.gemini.timeout', 120);
        $this->connectTimeout = (int) config('services.gemini.connect_timeout', 30);
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

        $contents = $history;
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $payload = [
            'contents' => $contents,
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ]
        ];

        try {
            $response = Http::withOptions([
                'verify' => $this->verifySsl,
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
            ])->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text');
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $errorMsg = $response->json('error.message', 'Erro desconhecido na API do Gemini.');
            return "Erro na API: " . $errorMsg;

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            return "Erro na comunicação: " . $e->getMessage();
        }
    }
}
