<?php

namespace Tests\Unit;

use App\Services\DatasetSemanticDescriptionService;
use PHPUnit\Framework\TestCase;

class DatasetSemanticDescriptionServiceTest extends TestCase
{
    public function test_infer_schema_sets_target_features_and_identifiers(): void
    {
        $service = new DatasetSemanticDescriptionService();

        $schema = $service->inferSchema(['patient_id', 'symptom', 'diagnosis']);

        $this->assertSame('diagnosis', $schema['target']);
        $this->assertSame(['patient_id', 'symptom'], $schema['features']);
        $this->assertSame(['patient_id'], $schema['identifiers']);
    }

    public function test_generate_semantic_description_ignores_identifier_features(): void
    {
        $service = new DatasetSemanticDescriptionService();
        $schema = [
            'target' => 'diagnosis',
            'features' => ['patient_id', 'symptom'],
            'identifiers' => ['patient_id'],
        ];
        $row = [
            'patient_id' => '123',
            'symptom' => 'fever',
            'diagnosis' => 'flu',
        ];

        $description = $service->generateSemanticDescription($row, $schema);

        $this->assertStringContainsString("'symptom' is 'fever'", $description);
        $this->assertStringNotContainsString("'patient_id' is '123'", $description);
        $this->assertStringContainsString("'diagnosis' is 'flu'", $description);
    }
}
