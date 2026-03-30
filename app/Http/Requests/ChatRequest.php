<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $snakeCaseConversationId = $this->input('conversation_id');
        $camelCaseConversationId = $this->input('conversationId');

        $normalizedConversationId = $snakeCaseConversationId ?? $camelCaseConversationId;

        if ($normalizedConversationId === '' || $normalizedConversationId === 'null') {
            $normalizedConversationId = null;
        }

        $this->merge([
            'conversation_id' => $normalizedConversationId,
        ]);
    }

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
            'message' => 'required|string',
            'conversation_id' => 'nullable|uuid|exists:conversations,id',
        ];
    }
}
