<?php

namespace Tests\Unit;

use App\Models\Dataset;
use App\Services\DatasetContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_semantic_descriptions_with_limit(): void
    {
        Storage::fake('local');
        $service = new DatasetContextService();

        Dataset::create([
            'original_filename' => 'a.csv',
            'storage_path' => 'datasets/a.csv',
            'parsed_path' => 'parsed/a.json',
        ]);

        Storage::disk('local')->put('parsed/a.json', json_encode([
            'metadata' => [
                'source' => 'a.csv',
                'schema_inference' => [
                    'target' => 'diagnostico',
                    'features' => ['sintoma', 'idade'],
                ],
            ],
            'records' => [
                [
                    'semantic_description' => 'Linha 1',
                    'original' => ['sintoma' => 'febre', 'idade' => '40', 'diagnostico' => 'viral'],
                ],
                [
                    'semantic_description' => 'Linha 2',
                    'original' => ['sintoma' => 'tosse', 'idade' => '55', 'diagnostico' => 'bacteriano'],
                ],
            ],
        ]));

        $context = $service->buildContext(1);

        $this->assertStringContainsString('Dataset: a.csv', $context);
        $this->assertStringContainsString('Target clínico principal: diagnostico', $context);
        $this->assertStringContainsString('Distribuição de classes (amostra): viral: 1', $context);
        $this->assertStringContainsString('Linha 1', $context);
        $this->assertStringNotContainsString('Linha 2', $context);
    }
}
