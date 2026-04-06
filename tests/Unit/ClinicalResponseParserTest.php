<?php

namespace Tests\Unit;

use App\Services\ClinicalResponseParser;
use PHPUnit\Framework\TestCase;

class ClinicalResponseParserTest extends TestCase
{
    public function test_parses_structured_json_and_limits_to_three_diagnoses(): void
    {
        $parser = new ClinicalResponseParser();

        $payload = $parser->parse(json_encode([
            'stage' => 'diagnostic_refinement',
            'summary' => 'quadro respiratorio em investigacao',
            'missing_information' => ['saturacao'],
            'follow_up_questions' => ['Tem falta de ar', 'Ha febre'],
            'diagnoses' => [
                ['hypothesis' => 'Pneumonia', 'certainty' => 'alta'],
                ['hypothesis' => 'Bronquite', 'certainty' => 'media'],
                ['hypothesis' => 'Covid-19', 'certainty' => 'low'],
                ['hypothesis' => 'Asma', 'certainty' => 'media'],
            ],
            'answer' => 'Resposta estruturada.',
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame('diagnostic_refinement', $payload['stage']);
        $this->assertSame(['Tem falta de ar?', 'Ha febre?'], $payload['follow_up_questions']);
        $this->assertCount(3, $payload['diagnoses']);
        $this->assertSame('baixa', $payload['diagnoses'][2]['certainty']);
    }

    public function test_falls_back_to_plain_text_response_when_json_is_invalid(): void
    {
        $parser = new ClinicalResponseParser();

        $payload = $parser->parse("Preciso refinar melhor o caso.\n1. Quando a dor comecou?\n2. Ha falta de ar?");

        $this->assertSame('anamnesis', $payload['stage']);
        $this->assertSame("Preciso refinar melhor o caso.\n1. Quando a dor comecou?\n2. Ha falta de ar?", $payload['answer']);
        $this->assertSame(['Quando a dor comecou?', 'Ha falta de ar?'], $payload['follow_up_questions']);
    }
}