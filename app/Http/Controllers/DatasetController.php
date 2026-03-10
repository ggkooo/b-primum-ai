<?php

namespace App\Http\Controllers;

use App\Http\Requests\DatasetUploadRequest;
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
            
            // Store the file in 'datasets' directory within 'app' (storage/app/datasets)
            $path = $file->store('datasets');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Dataset uploaded successfully',
                'data' => [
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ]
            ], 201);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No file uploaded'
        ], 400);
    }
}
