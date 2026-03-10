<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatasetUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset' => 'required|file|mimes:csv,txt|max:10240', // Limit to 10MB
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dataset.required' => 'A dataset file is required.',
            'dataset.file' => 'The uploaded file must be a valid file.',
            'dataset.mimes' => 'The dataset must be a CSV file.',
            'dataset.max' => 'The dataset file may not be greater than 10MB.',
        ];
    }
}
