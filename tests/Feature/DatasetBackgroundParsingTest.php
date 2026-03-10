<?php

namespace Tests\Feature;

use App\Jobs\ParseDatasetJob;
use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetBackgroundParsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_dispatches_parse_dataset_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $csvContent = "symptom1,symptom2,diagnosis\nyes,no,flu";
        $file = UploadedFile::fake()->createWithContent('test_dataset.csv', $csvContent);

        $response = $this->postJson('/api/datasets/upload', [
            'dataset' => $file,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Dataset uploaded successfully. Parsing started in background.',
            ]);

        $dataset = Dataset::first();
        
        Queue::assertPushed(ParseDatasetJob::class, function ($job) use ($dataset) {
            return $job->dataset->id === $dataset->id;
        });
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
    }
}
