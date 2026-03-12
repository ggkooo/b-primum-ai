<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_csv_dataset(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test_dataset.csv', 100, 'text/csv');

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

        // Check if file exists in storage/app/datasets (default disk is local)
        $storedPath = $response->json('data.path');
        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_upload_requires_a_file(): void
    {
        $response = $this->postJson('/api/datasets/upload', [], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dataset']);
    }

    public function test_upload_requires_csv_mimetype(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test_image.jpg', 100, 'image/jpeg');

        $response = $this->postJson('/api/datasets/upload', [
            'dataset' => $file,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dataset']);
    }
}
