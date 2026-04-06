<?php

namespace Tests\Unit;

use App\Services\OllamaPromptBuilder;
use PHPUnit\Framework\TestCase;

class OllamaPromptBuilderTest extends TestCase
{
    public function test_build_ollama_system_instruction_is_lightweight_and_structured(): void
    {
        $builder = new OllamaPromptBuilder();

        $prompt = $builder->buildOllamaSystemInstruction('diagnostic_refinement', [
            'summary' => 'dor toracica ha 2 dias',
            'diagnoses' => [
                ['hypothesis' => 'angina', 'certainty' => 'media'],
            ],
        ]);

        $this->assertStringContainsString('Modelfile', $prompt);
        $this->assertStringContainsString('JSON valido', $prompt);
        $this->assertStringContainsString('dataset_records', $prompt);
        $this->assertStringContainsString('diagnostic_refinement', $prompt);
        $this->assertStringContainsString('Nunca solicite dados pessoais ou sensiveis', $prompt);
        $this->assertStringContainsString('Nunca repita perguntas', $prompt);
    }
}
