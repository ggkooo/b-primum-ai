<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatHistoryBuilderService;
use App\Services\ChatService;
use App\Services\ClinicalWorkflowService;
use App\Services\ConversationResolverService;
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
            'clinical_stage' => 'anamnesis',
        ]);

        $workflowMock = Mockery::mock(ClinicalWorkflowService::class);
        $resolverMock = Mockery::mock(ConversationResolverService::class);
        $historyBuilderMock = Mockery::mock(ChatHistoryBuilderService::class);

        $resolverMock->shouldReceive('resolve')
            ->once()
            ->with($user, 'Teste de chat', $conversation->id)
            ->andReturn($conversation);

        $historyBuilderMock->shouldReceive('buildForConversation')
            ->once()
            ->with($conversation, 1)
            ->andReturn([
                ['role' => 'assistant', 'content' => 'Como posso ajudar?'],
            ]);

        $workflowMock->shouldReceive('generateResponse')
            ->once()
            ->with($conversation, 'Teste de chat', Mockery::type('array'))
            ->andReturn([
                'response' => 'Resposta IA',
                'stage' => 'diagnostic_refinement',
                'summary' => 'Resumo do caso',
                'missing_information' => ['temperatura'],
                'follow_up_questions' => ['Ha febre?'],
                'diagnoses' => [
                    [
                        'hypothesis' => 'Gripe',
                        'certainty' => 'media',
                        'rationale' => 'Compativel com sintomas inespecificos.',
                        'supporting_evidence' => ['mal-estar'],
                        'warning_signs' => ['dispneia'],
                        'next_steps' => ['hidratar'],
                    ],
                ],
            ]);

        $service = new ChatService($workflowMock, $resolverMock, $historyBuilderMock);
        $result = $service->handleMessage($user, 'Teste de chat', $conversation->id);

        $this->assertSame($conversation->id, $result['conversation_id']);
        $this->assertSame('Resposta IA', $result['response']);
        $this->assertSame('diagnostic_refinement', $result['stage']);
        $this->assertSame(['Ha febre?'], $result['follow_up_questions']);
        $this->assertCount(1, $result['diagnoses']);

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
