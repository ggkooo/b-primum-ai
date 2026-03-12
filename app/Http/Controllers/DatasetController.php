<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetUploadRequest;
use App\Models\Dataset;
use App\Services\DatasetService;
use Illuminate\Http\JsonResponse;

class DatasetController extends Controller
{
    public function __construct(private readonly DatasetService $datasetService)
    {
    }

    /**
     * Handle the dataset upload.
     *
     * @param  \App\Http\Requests\DatasetUploadRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(DatasetUploadRequest $request): JsonResponse
    {
        $file = $request->file('dataset');
        $dataset = $this->datasetService->uploadAndQueueParse($file);

        return $this->success([
            'id' => $dataset->id,
            'filename' => $dataset->original_filename,
            'path' => $dataset->storage_path,
            'size' => $file->getSize(),
        ], 'Dataset uploaded successfully. Parsing started in background.', 201);
    }

    /**
     * Trigger parsing for a specific dataset.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function parse(int $id): JsonResponse
    {
        $dataset = Dataset::findOrFail($id);

        if ($this->datasetService->parse($dataset)) {
            return $this->success([
                'id' => $dataset->id,
                'parsed_path' => $dataset->parsed_path,
                'metadata' => $dataset->metadata,
            ], 'Dataset parsed successfully');
        }

        return $this->error('Parsing failed', 500);
    }
}
