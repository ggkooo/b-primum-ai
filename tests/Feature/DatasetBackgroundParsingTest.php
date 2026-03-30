<?php

namespace Tests\Feature;

use App\Jobs\ParseDatasetJob;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetBackgroundParsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ollama.generate_embeddings' => false]);
    }

    public function test_upload_parses_dataset_synchronously(): void
    {
        Storage::fake('local');

        $csvContent = "symptom1,symptom2,diagnosis\nyes,no,flu";
        $file = UploadedFile::fake()->createWithContent('test_dataset.csv', $csvContent);

        $response = $this->postJson('/api/datasets/upload', [
            'dataset' => $file,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Dataset uploaded and parsed successfully',
            ]);

        $dataset = Dataset::first();

        $this->assertNotNull($dataset->parsed_path);
        $this->assertSame(1, DatasetRecord::where('dataset_id', $dataset->id)->count());
    }

    public function test_parse_dataset_job_executes_parsing_logic(): void
    {
        Storage::fake('local');

        $csvContent = "feature1,target\nval1,res1";
        $path = Storage::put('datasets/test.csv', $csvContent);

        $dataset = Dataset::create([
            'original_filename' => 'test.csv',
            'storage_path' => 'datasets/test.csv',
        ]);

        $job = new ParseDatasetJob($dataset);
        $job->handle(new \App\Services\DatasetParserService());

        $dataset->refresh();
        $this->assertNotNull($dataset->parsed_path);
        Storage::disk('local')->assertExists($dataset->parsed_path);
        $this->assertSame(1, DatasetRecord::where('dataset_id', $dataset->id)->count());
    }
}
