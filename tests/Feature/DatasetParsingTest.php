<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetParsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_and_parse_dataset(): void
    {
        Storage::fake('local');

        // 1. Upload
        $csvContent = "symptom1,symptom2,diagnosis\nyes,no,flu\nno,yes,cold";
        $file = UploadedFile::fake()->createWithContent('test_dataset.csv', $csvContent);

        $response = $this->postJson('/api/datasets/upload', [
            'dataset' => $file,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(201);
        $datasetId = $response->json('data.id');

        // 2. Parse via API
        $parseResponse = $this->postJson("/api/datasets/{$datasetId}/parse", [], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $parseResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Dataset parsed successfully',
            ]);

        $dataset = Dataset::find($datasetId);
        $this->assertNotNull($dataset->parsed_path);
        
        $parsedContent = json_decode(Storage::get($dataset->parsed_path), true);
        
        // 3. Verify semantic structure
        $this->assertCount(2, $parsedContent['records']);
        $this->assertEquals("test_dataset.csv", $parsedContent['metadata']['source']);
        $this->assertStringContainsString("This record describes an instance where", $parsedContent['records'][0]['semantic_description']);
        $this->assertStringContainsString("symptom1", $parsedContent['records'][0]['semantic_description']);
        $this->assertStringContainsString("flu", $parsedContent['records'][0]['semantic_description']);
    }

    public function test_can_parse_via_artisan_command(): void
    {
        Storage::fake('local');

        $csvContent = "id,feature_a,target\n1,val1,result1";
        $path = Storage::put('datasets/test.csv', $csvContent);

        $dataset = Dataset::create([
            'original_filename' => 'test.csv',
            'storage_path' => 'datasets/test.csv',
        ]);

        $this->artisan("dataset:parse {$dataset->id}")
            ->assertExitCode(0)
            ->expectsOutput("Parsing dataset: test.csv...")
            ->expectsOutput("Dataset parsed successfully!");

        $dataset->refresh();
        $this->assertNotNull($dataset->parsed_path);
        
        $parsedContent = json_decode(Storage::get($dataset->parsed_path), true);
        $this->assertEquals("target", $parsedContent['metadata']['schema_inference']['target']);
        $this->assertContains("id", $parsedContent['metadata']['schema_inference']['identifiers']);
    }
}
