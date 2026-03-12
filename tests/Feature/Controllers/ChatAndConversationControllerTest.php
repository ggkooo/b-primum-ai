<?php

namespace Tests\Feature\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ConversationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ChatAndConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_endpoint_delegates_to_chat_service(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversationUuid = (string) \Illuminate\Support\Str::uuid();
        $chatServiceMock = Mockery::mock(ChatService::class);
        $chatServiceMock->shouldReceive('handleMessage')
            ->once()
            ->with($user, 'Oi', null)
            ->andReturn([
                'conversation_id' => $conversationUuid,
                'response' => 'Resposta mock',
            ]);
        $this->app->instance(ChatService::class, $chatServiceMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Oi',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.conversation_id', $conversationUuid)
            ->assertJsonPath('data.response', 'Resposta mock');
    }

    public function test_conversation_endpoints_delegate_to_conversation_service(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Mock conversation',
            'last_message_at' => now(),
        ]);

        $serviceMock = Mockery::mock(ConversationService::class);
        $serviceMock->shouldReceive('listForUser')
            ->once()
            ->with($user)
            ->andReturn(new Collection([$conversation]));
        $serviceMock->shouldReceive('findForUser')
            ->once()
            ->with($user, Mockery::type('string'))
            ->andReturn($conversation);
        $this->app->instance(ConversationService::class, $serviceMock);

        $listResponse = $this->getJson('/api/conversations', [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $showResponse = $this->getJson("/api/conversations/{$conversation->id}", [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.conversations.0.id', $conversation->id);

        $showResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.conversation.id', $conversation->id);
    }
}
