<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetParsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ollama.generate_embeddings' => false]);
    }

    public function test_can_upload_and_parse_dataset(): void
    {
        Storage::fake('local');

        $csvContent = "symptom1,symptom2,diagnosis\nyes,no,flu\nno,yes,cold";
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

        $datasetId = $response->json('data.id');

        $dataset = Dataset::find($datasetId);
        $this->assertNotNull($dataset->parsed_path);
        
        $parsedContent = json_decode(Storage::get($dataset->parsed_path), true);
        
        $this->assertCount(2, $parsedContent['records']);
        $this->assertEquals("test_dataset.csv", $parsedContent['metadata']['source']);
        $this->assertStringContainsString("This record describes an instance where", $parsedContent['records'][0]['semantic_description']);
        $this->assertStringContainsString("symptom1", $parsedContent['records'][0]['semantic_description']);
        $this->assertStringContainsString("flu", $parsedContent['records'][0]['semantic_description']);

        $this->assertSame(2, DatasetRecord::where('dataset_id', $datasetId)->count());
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
