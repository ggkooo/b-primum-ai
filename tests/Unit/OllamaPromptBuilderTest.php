<?php

namespace Tests\Unit;

use App\Services\OllamaPromptBuilder;
use PHPUnit\Framework\TestCase;

class OllamaPromptBuilderTest extends TestCase
{
    public function test_build_ollama_system_instruction_is_lightweight_and_diagnostic_oriented(): void
    {
        $builder = new OllamaPromptBuilder();

        $prompt = $builder->buildOllamaSystemInstruction();

        $this->assertStringContainsString('Modelfile', $prompt);
        $this->assertStringContainsString('ate 3 diagnosticos', $prompt);
        $this->assertStringContainsString('sinais de alerta', $prompt);
    }
}
