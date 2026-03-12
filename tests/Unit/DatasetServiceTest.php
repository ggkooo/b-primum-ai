<?php

namespace Tests\Unit;

use App\Jobs\ParseDatasetJob;
use App\Models\Dataset;
use App\Services\DatasetParserService;
use App\Services\DatasetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DatasetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_and_queue_parse_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $service = new DatasetService(new DatasetParserService());
        $file = UploadedFile::fake()->createWithContent('file.csv', "a,b\n1,2");

        $dataset = $service->uploadAndQueueParse($file);

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id]);
        Storage::disk('local')->assertExists($dataset->storage_path);
        Queue::assertPushed(ParseDatasetJob::class);
    }

    public function test_parse_delegates_to_parser_service(): void
    {
        $parserMock = Mockery::mock(DatasetParserService::class);
        $service = new DatasetService($parserMock);

        $dataset = Dataset::create([
            'original_filename' => 'x.csv',
            'storage_path' => 'datasets/x.csv',
        ]);

        $parserMock->shouldReceive('parse')->once()->with($dataset)->andReturn(true);

        $this->assertTrue($service->parse($dataset));
    }
}
