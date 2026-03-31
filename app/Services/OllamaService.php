<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $apiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $baseUrl;
    protected bool $verifySsl;
    protected ?string $caBundle;
    protected string $authHeader;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct(private readonly OllamaPromptBuilder $promptBuilder)
    {
        $this->apiKey = (string) config('services.ollama.api_key');
        $this->model = (string) config('services.ollama.model', 'llama3.1');
        $this->embeddingModel = (string) config('services.ollama.embedding_model', 'nomic-embed-text');
        $this->baseUrl = rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $this->verifySsl = (bool) config('services.ollama.verify_ssl', true);
        $this->caBundle = config('services.ollama.ca_bundle');
        $this->caBundle = is_string($this->caBundle) && trim($this->caBundle) !== ''
            ? trim($this->caBundle)
            : null;
        $this->authHeader = strtolower((string) config('services.ollama.auth_header', 'x-api-key'));
        $this->timeout = (int) config('services.ollama.timeout', 0);
        $this->connectTimeout = (int) config('services.ollama.connect_timeout', 0);
    }

    /**
     * @param array<int, array{role:string,content:string}> $history
     */
    public function generateResponse(string $prompt, array $history = [], ?string $datasetContext = null): ?string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->promptBuilder->buildOllamaSystemInstruction(),
            ],
            ...($this->buildDatasetContextMessages($datasetContext)),
            ...$history,
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        try {
            $response = $this->buildHttpClient()->post($this->baseUrl . '/api/chat', $payload);

            if ($response->successful()) {
                return $response->json('message.content')
                    ?? $response->json('response')
                    ?? 'Nao foi possivel gerar uma resposta.';
            }

            Log::error('Ollama API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiProviderException('Erro na API de IA.', $response->status());
        } catch (\Exception $e) {
            Log::error('Ollama Service Exception: ' . $e->getMessage());

            if ($e instanceof AiProviderException) {
                throw $e;
            }

            throw new AiProviderException('Erro na comunicacao com o provedor de IA.', 502);
        }
    }

    /**
     * @return array<int, float>|null
     */
    public function generateEmbedding(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $payload = [
            'model' => $this->embeddingModel,
            'prompt' => $text,
        ];

        try {
            $response = $this->buildHttpClient()->post($this->baseUrl . '/api/embeddings', $payload);

            if (!$response->successful()) {
                Log::warning('Ollama Embedding API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $embedding = $response->json('embedding')
                ?? $response->json('embeddings.0');

            return is_array($embedding) ? $embedding : null;
        } catch (\Exception $e) {
            Log::warning('Ollama Embedding Exception: ' . $e->getMessage());

            return null;
        }
    }

    private function buildHttpClient()
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey !== '') {
            if ($this->authHeader === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            } elseif ($this->authHeader === 'both') {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
                $headers['X-API-Key'] = $this->apiKey;
            } else {
                $headers['X-API-Key'] = $this->apiKey;
            }
        }

        $options = [
            'verify' => $this->caBundle ?? $this->verifySsl,
            // Guzzle interprets 0 as no timeout; keeping it explicit avoids Laravel's default 30s timeout.
            'timeout' => max(0, (float) $this->timeout),
            'connect_timeout' => max(0, (float) $this->connectTimeout),
        ];

        return Http::withOptions($options)->withHeaders($headers);
    }

    /**
     * @return array<int, array{role:string,content:string}>
     */
    private function buildDatasetContextMessages(?string $datasetContext): array
    {
        if (!is_string($datasetContext) || trim($datasetContext) === '') {
            return [];
        }

        return [
            [
                'role' => 'system',
                'content' => "Contexto recuperado do dataset vetorizado (RAG):\n"
                    . trim($datasetContext)
                    . "\nUse esse contexto como base primaria para responder quando for relevante ao caso.",
            ],
        ];
    }
}
