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

    public function test_user_can_delete_own_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa para excluir',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Mensagem que deve sumir em cascata',
        ]);

        $response = $this->deleteJson("/api/conversations/{$conversation->id}", [], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Conversa privada',
            'last_message_at' => now(),
        ]);

        $response = $this->deleteJson("/api/conversations/{$conversation->id}", [], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
        ]);
    }
}
