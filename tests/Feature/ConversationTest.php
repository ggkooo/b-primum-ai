<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_only_own_conversations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Conversation::create([
            'user_id' => $user->id,
            'title' => 'Minha conversa',
            'last_message_at' => now(),
        ]);

        Conversation::create([
            'user_id' => $otherUser->id,
            'title' => 'Conversa de outro usuario',
            'last_message_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/conversations', [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.conversations');
    }

    public function test_user_can_view_own_conversation_with_messages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa clinica',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Tenho febre',
        ]);

        $response = $this->getJson("/api/conversations/{$conversation->id}", [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.conversation.id', $conversation->id)
            ->assertJsonCount(1, 'data.conversation.messages');
    }
}
