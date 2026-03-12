<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;

class ConversationResolverService
{
    public function resolve(User $user, string $userMessage, ?string $conversationId): Conversation
    {
        // If conversation_id is provided, use that conversation
        if ($conversationId) {
            return Conversation::where('user_id', $user->id)->findOrFail($conversationId);
        }

        // Otherwise create a new conversation
        return Conversation::create([
            'user_id' => $user->id,
            'title' => substr($userMessage, 0, 50) . '...',
            'last_message_at' => now(),
        ]);
    }
}
