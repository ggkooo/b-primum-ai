<?php

namespace Tests\Unit;

use App\Models\Dataset;
use App\Models\DatasetRecord;
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
            'records' => [
                ['semantic_description' => 'Linha 1'],
                ['semantic_description' => 'Linha 2'],
            ],
        ]));

        $context = $service->buildContext(1);

        $this->assertStringContainsString('Linha 1', $context);
        $this->assertStringNotContainsString('Linha 2', $context);
    }

    public function test_truncates_database_context_using_configured_max_chars(): void
    {
        config(['services.ollama.chat_context_max_chars' => 60]);

        $dataset = Dataset::create([
            'original_filename' => 'context.csv',
            'storage_path' => 'datasets/context.csv',
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'record_index' => 0,
            'original' => ['symptom' => 'dor abdominal'],
            'semantic_description' => str_repeat('dor abdominal importante ', 8),
            'embedding' => null,
        ]);

        $context = app(DatasetContextService::class)->buildContext(10, 'dor abdominal', 10);

        $this->assertLessThanOrEqual(60, mb_strlen($context));
        $this->assertStringEndsWith('...', $context);
    }
}
