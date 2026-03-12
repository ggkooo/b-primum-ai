<?php

namespace Tests\Unit;

use App\Http\Requests\DatasetUploadRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DatasetUploadRequestTest extends TestCase
{
    public function test_validation_passes_with_csv_file(): void
    {
        $request = new DatasetUploadRequest();

        $validator = Validator::make([
            'dataset' => UploadedFile::fake()->create('dataset.csv', 100, 'text/csv'),
        ], $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_non_csv_file(): void
    {
        $request = new DatasetUploadRequest();

        $validator = Validator::make([
            'dataset' => UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dataset', $validator->errors()->toArray());
    }
}
