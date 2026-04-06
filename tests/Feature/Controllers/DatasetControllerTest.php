<?php

namespace Tests\Feature\Controllers;

use App\Models\Dataset;
use App\Services\DatasetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DatasetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_endpoint_delegates_to_dataset_service(): void
    {
        $serviceMock = Mockery::mock(DatasetService::class);
        $serviceMock->shouldReceive('uploadAndParse')
            ->once()
            ->andReturn(new Dataset([
                'id' => 7,
                'original_filename' => 'dataset.csv',
                'storage_path' => 'datasets/fake.csv',
                'parsed_path' => 'parsed/fake.json',
                'metadata' => ['total_records' => 1],
            ]));

        $this->app->instance(DatasetService::class, $serviceMock);

        $response = $this->postJson('/api/datasets/upload', [
            'dataset' => UploadedFile::fake()->create('dataset.csv', 5, 'text/csv'),
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.filename', 'dataset.csv');
    }

    public function test_parse_endpoint_returns_error_when_service_fails(): void
    {
        $dataset = Dataset::create([
            'original_filename' => 'd.csv',
            'storage_path' => 'datasets/d.csv',
        ]);

        $serviceMock = Mockery::mock(DatasetService::class);
        $serviceMock->shouldReceive('parse')->once()->with(Mockery::type(Dataset::class))->andReturn(false);
        $this->app->instance(DatasetService::class, $serviceMock);

        $response = $this->postJson("/api/datasets/{$dataset->id}/parse", [], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Parsing failed',
            ]);
    }
}
