<?php

namespace Tests\Unit;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Services\DatasetRecordSearchService;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DatasetRecordSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_lexical_ranking_when_embeddings_are_unavailable(): void
    {
        config(['services.ollama.generate_embeddings' => true]);

        $dataset = Dataset::create([
            'original_filename' => 'triagem.csv',
            'storage_path' => 'datasets/triagem.csv',
        ]);

        $relevant = DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'record_index' => 0,
            'original' => ['case' => 'relevant'],
            'semantic_description' => 'Paciente com dor no peito, falta de ar e febre leve.',
            'embedding' => null,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'record_index' => 1,
            'original' => ['case' => 'secondary'],
            'semantic_description' => 'Paciente com tosse seca e fadiga.',
            'embedding' => null,
        ]);

        DatasetRecord::create([
            'dataset_id' => $dataset->id,
            'record_index' => 2,
            'original' => ['case' => 'irrelevant'],
            'semantic_description' => 'Rotina de acompanhamento nutricional sem queixa respiratoria.',
            'embedding' => null,
        ]);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateEmbedding')
            ->once()
            ->with('dor no peito e falta de ar')
            ->andReturn(null);

        $service = new DatasetRecordSearchService($ollamaMock);
        $results = $service->search('dor no peito e falta de ar', 1, 10);

        $this->assertCount(1, $results);
        $this->assertSame($relevant->id, $results->first()->id);
    }
}