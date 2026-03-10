<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetUploadRequest;
use App\Jobs\ParseDatasetJob;
use App\Models\Dataset;
use App\Services\DatasetParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DatasetController extends Controller
{
    /**
     * Handle the dataset upload.
     *
     * @param  \App\Http\Requests\DatasetUploadRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(DatasetUploadRequest $request): JsonResponse
    {
        if ($request->hasFile('dataset')) {
            $file = $request->file('dataset');
            
            // Store the file in 'datasets' directory
            $path = $file->store('datasets');
            
            // Persist the dataset in the database
            $dataset = Dataset::create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
            ]);

            // Dispatch the background parsing job
            ParseDatasetJob::dispatch($dataset);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Dataset uploaded successfully. Parsing started in background.',
                'data' => [
                    'id' => $dataset->id,
                    'filename' => $dataset->original_filename,
                    'path' => $dataset->storage_path,
                    'size' => $file->getSize(),
                ]
            ], 201);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No file uploaded'
        ], 400);
    }

    /**
     * Trigger parsing for a specific dataset.
     *
     * @param  int  $id
     * @param  \App\Services\DatasetParserService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function parse(int $id, DatasetParserService $service): JsonResponse
    {
        $dataset = Dataset::findOrFail($id);

        if ($service->parse($dataset)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Dataset parsed successfully',
                'data' => [
                    'id' => $dataset->id,
                    'parsed_path' => $dataset->parsed_path,
                    'metadata' => $dataset->metadata,
                ]
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Parsing failed'
        ], 500);
    }
}
