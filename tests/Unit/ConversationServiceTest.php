<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_for_user_returns_only_user_conversations_ordered(): void
    {
        $service = new ConversationService();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $older = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Older',
            'last_message_at' => now()->subDay(),
        ]);

        $newer = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Newer',
            'last_message_at' => now(),
        ]);

        Conversation::create([
            'user_id' => $otherUser->id,
            'title' => 'Other',
            'last_message_at' => now()->addMinute(),
        ]);

        $result = $service->listForUser($user);

        $this->assertCount(2, $result);
        $this->assertSame($newer->id, $result[0]->id);
        $this->assertSame($older->id, $result[1]->id);
    }

    public function test_find_for_user_returns_conversation_with_messages_ordered(): void
    {
        $service = new ConversationService();
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Clinical',
            'last_message_at' => now(),
        ]);

        $early = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'First',
        ]);

        $late = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => 'Second',
        ]);

        $found = $service->findForUser($user, $conversation->id);

        $this->assertSame($conversation->id, $found->id);
        $this->assertCount(2, $found->messages);
        $this->assertSame($early->id, $found->messages[0]->id);
        $this->assertSame($late->id, $found->messages[1]->id);
    }

    public function test_find_for_user_throws_for_other_users_conversation(): void
    {
        $service = new ConversationService();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'last_message_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);
        $service->findForUser($intruder, $conversation->id);
    }

    public function test_delete_for_user_removes_conversation(): void
    {
        $service = new ConversationService();
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Delete me',
            'last_message_at' => now(),
        ]);

        $service->deleteForUser($user, $conversation->id);

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_delete_for_user_throws_for_other_users_conversation(): void
    {
        $service = new ConversationService();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Private delete test',
            'last_message_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service->deleteForUser($intruder, $conversation->id);
    }
}
