<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatHistoryBuilderService;
use App\Services\ConversationResolverService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationResolverAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_creates_new_conversation_when_id_is_null(): void
    {
        $resolver = new ConversationResolverService();
        $user = User::factory()->create();

        // When conversation_id is null, a new conversation should be created
        $conversation = $resolver->resolve($user, 'Mensagem de teste para abrir conversa', null);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        $this->assertStringEndsWith('...', $conversation->title);
    }

    public function test_resolve_returns_existing_conversation_for_same_user(): void
    {
        $resolver = new ConversationResolverService();
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Existing',
            'last_message_at' => now(),
        ]);

        $resolved = $resolver->resolve($user, 'ignored', $conversation->id);

        $this->assertSame($conversation->id, $resolved->id);
    }

    public function test_resolve_throws_for_conversation_from_another_user(): void
    {
        $resolver = new ConversationResolverService();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'last_message_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);
        $resolver->resolve($intruder, 'ignored', $conversation->id);
    }

    public function test_history_builder_returns_messages_in_chronological_format(): void
    {
        $builder = new ChatHistoryBuilderService();
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'History',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Pergunta',
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => 'Resposta',
        ]);

        $history = $builder->buildForConversation($conversation);

        $this->assertCount(2, $history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Pergunta', $history[0]['parts'][0]['text']);
        $this->assertSame('model', $history[1]['role']);
        $this->assertSame('Resposta', $history[1]['parts'][0]['text']);
    }
}
