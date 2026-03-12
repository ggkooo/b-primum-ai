<?php

namespace App\Services;

use App\Jobs\ParseDatasetJob;
use App\Models\Dataset;
use Illuminate\Http\UploadedFile;

class DatasetService
{
    public function __construct(private readonly DatasetParserService $parserService)
    {
    }

    public function uploadAndQueueParse(UploadedFile $file): Dataset
    {
        $path = $file->store('datasets');

        $dataset = Dataset::create([
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
        ]);

        ParseDatasetJob::dispatch($dataset);

        return $dataset;
    }

    public function parse(Dataset $dataset): bool
    {
        return $this->parserService->parse($dataset);
    }
}
