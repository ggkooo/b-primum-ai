<?php

namespace Tests\Feature;

use App\Exceptions\AiProviderException;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_start_chat_and_receive_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Resposta simulada da IA');
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Estou com dor de cabeca',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'response' => 'Resposta simulada da IA',
                ],
            ]);

        $conversationId = $response->json('data.conversation_id');
        $this->assertNotNull($conversationId);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Estou com dor de cabeca',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'model',
            'content' => 'Resposta simulada da IA',
        ]);
    }

    public function test_empty_conversation_id_creates_new_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Existing conversation should not be auto-continued
        Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa anterior',
            'last_message_at' => now(),
        ]);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Nova conversa criada');
        $this->app->instance(OllamaService::class, $ollamaMock);

        // Sending message without conversation_id should create a new conversation
        $response = $this->postJson('/api/chat', [
            'message' => 'Quero iniciar um novo assunto',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200);

        // Should have 2 conversations: the old one and the new one
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_camel_case_conversation_id_reuses_existing_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa em andamento',
            'last_message_at' => now(),
        ]);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Resposta na mesma conversa');
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Mensagem de continuidade',
            'conversationId' => $conversation->id,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->assertDatabaseCount('conversations', 1);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Mensagem de continuidade',
        ]);
    }

    public function test_user_cannot_send_message_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Conversa privada',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Mensagem original',
        ]);

        Sanctum::actingAs($intruder);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldNotReceive('generateResponse');
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Tentando invadir',
            'conversation_id' => $conversation->id,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(404);
    }

    public function test_returns_error_when_ai_provider_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andThrow(new AiProviderException('Erro na API de IA.', 504));
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Teste com falha upstream',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(504)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Erro na API de IA.');
    }
}
