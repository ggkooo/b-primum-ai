<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatHistoryBuilderService;
use App\Services\ChatService;
use App\Services\ConversationResolverService;
use App\Services\DatasetContextService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_messages_and_returns_response(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Start',
            'last_message_at' => now()->subMinute(),
        ]);

        $geminiMock = Mockery::mock(GeminiService::class);
        $datasetContextMock = Mockery::mock(DatasetContextService::class);
        $resolverMock = Mockery::mock(ConversationResolverService::class);
        $historyBuilderMock = Mockery::mock(ChatHistoryBuilderService::class);

        $resolverMock->shouldReceive('resolve')
            ->once()
            ->with($user, 'Teste de chat', $conversation->id)
            ->andReturn($conversation);

        $historyBuilderMock->shouldReceive('buildForConversation')
            ->once()
            ->with($conversation)
            ->andReturn([
                ['role' => 'user', 'parts' => [['text' => 'Teste de chat']]],
            ]);

        $datasetContextMock->shouldReceive('buildContext')->once()->andReturn('Contexto');
        $geminiMock->shouldReceive('generateResponse')
            ->once()
            ->with('Teste de chat', Mockery::type('array'), 'Contexto')
            ->andReturn('Resposta IA');

        $service = new ChatService($geminiMock, $datasetContextMock, $resolverMock, $historyBuilderMock);
        $result = $service->handleMessage($user, 'Teste de chat', $conversation->id);

        $this->assertSame($conversation->id, $result['conversation_id']);
        $this->assertSame('Resposta IA', $result['response']);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Teste de chat',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => 'Resposta IA',
        ]);
    }
}
