<?php

namespace Tests\Unit;

use App\Services\GeminiPromptBuilder;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.base_url' => 'https://example.test/models/',
            'services.gemini.verify_ssl' => false,
            'services.gemini.timeout' => 10,
            'services.gemini.connect_timeout' => 5,
        ]);
    }

    public function test_generate_response_returns_text_on_success(): void
    {
        Http::fake([
            'https://example.test/models/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Resposta final'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiService(new GeminiPromptBuilder());
        $result = $service->generateResponse('Pergunta', [], 'Contexto');

        $this->assertSame('Resposta final', $result);
    }

    public function test_generate_response_returns_error_message_on_api_error(): void
    {
        Http::fake([
            'https://example.test/models/*' => Http::response([
                'error' => [
                    'message' => 'Falha de validacao',
                ],
            ], 400),
        ]);

        $service = new GeminiService(new GeminiPromptBuilder());
        $result = $service->generateResponse('Pergunta', [], 'Contexto');

        $this->assertSame('Erro na API: Falha de validacao', $result);
    }

    public function test_generate_response_returns_communication_error_on_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Network down');
        });

        $service = new GeminiService(new GeminiPromptBuilder());
        $result = $service->generateResponse('Pergunta', [], 'Contexto');

        $this->assertStringContainsString('Erro na comunicação: Network down', (string) $result);
    }
}
