<?php

namespace Tests\Unit;

use App\Http\Requests\ChatRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ChatRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_message_only(): void
    {
        $request = new ChatRequest();

        $validator = Validator::make([
            'message' => 'Estou com dor de cabeca',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_invalid_conversation_id(): void
    {
        $request = new ChatRequest();

        $validator = Validator::make([
            'message' => 'Teste',
            'conversation_id' => 999,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('conversation_id', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_existing_conversation_id(): void
    {
        $request = new ChatRequest();
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Teste',
            'last_message_at' => now(),
        ]);

        $validator = Validator::make([
            'message' => 'Teste',
            'conversation_id' => $conversation->id,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
