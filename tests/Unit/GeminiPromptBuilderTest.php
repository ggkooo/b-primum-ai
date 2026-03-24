<?php

namespace Tests\Unit;

use App\Services\GeminiPromptBuilder;
use PHPUnit\Framework\TestCase;

class GeminiPromptBuilderTest extends TestCase
{
    public function test_includes_context_and_mandatory_notice(): void
    {
        $builder = new GeminiPromptBuilder();

        $prompt = $builder->buildSystemInstruction('Contexto clinico base');

        $this->assertStringContainsString('Hipóteses diagnósticas iniciais', $prompt);
        $this->assertStringContainsString('Nível de urgência', $prompt);
        $this->assertStringContainsString('Próximas perguntas objetivas', $prompt);
        $this->assertStringContainsString('triagem assistida por IA', $prompt);
        $this->assertStringContainsString('Contexto clinico base', $prompt);
    }
}
