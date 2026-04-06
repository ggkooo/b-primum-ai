<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\OllamaPromptBuilder;
use App\Services\OllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ollama.api_key' => 'test-key',
            'services.ollama.model' => 'ollama-test-model',
            'services.ollama.embedding_model' => 'embedding-test-model',
            'services.ollama.base_url' => 'https://example.test',
            'services.ollama.verify_ssl' => false,
            'services.ollama.timeout' => 10,
            'services.ollama.connect_timeout' => 5,
        ]);
    }

    public function test_generate_response_returns_text_on_success(): void
    {
        Http::fake([
            'https://example.test/api/chat' => Http::response([
                'message' => [
                    'content' => 'Resposta final',
                ],
            ], 200),
        ]);

        $service = new OllamaService(new OllamaPromptBuilder());
        $result = $service->generateResponse('Pergunta');

        $this->assertSame('Resposta final', $result);

        Http::assertSent(function ($request) {
            $messages = $request['messages'];

            return $request->url() === 'https://example.test/api/chat'
                && $messages[0]['role'] === 'system'
                && str_contains($messages[0]['content'], 'Modelfile')
                && $messages[1]['role'] === 'user'
                && $messages[1]['content'] === 'Pergunta';
        });
    }

    public function test_generate_response_throws_exception_on_api_error(): void
    {
        Http::fake([
            'https://example.test/api/chat' => Http::response([
                'error' => 'Falha de validacao',
            ], 400),
        ]);

        $service = new OllamaService(new OllamaPromptBuilder());

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Erro na API de IA.');

        $service->generateResponse('Pergunta');
    }

    public function test_generate_response_throws_communication_exception_on_transport_failure(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Network down');
        });

        $service = new OllamaService(new OllamaPromptBuilder());

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Erro na comunicacao com o provedor de IA.');

        $service->generateResponse('Pergunta');
    }

    public function test_generate_embedding_returns_vector_on_success(): void
    {
        Http::fake([
            'https://example.test/api/embeddings' => Http::response([
                'embedding' => [0.1, 0.2, 0.3],
            ], 200),
        ]);

        $service = new OllamaService(new OllamaPromptBuilder());
        $embedding = $service->generateEmbedding('texto para embedding');

        $this->assertSame([0.1, 0.2, 0.3], $embedding);
    }
}
