<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ConversationService
{
    /**
     * @return Collection<int, Conversation>
     */
    public function listForUser(User $user): Collection
    {
        return $user->conversations()->orderBy('last_message_at', 'desc')->get();
    }

    public function findForUser(User $user, string $conversationId): Conversation
    {
        return Conversation::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->where('user_id', $user->id)->findOrFail($conversationId);
    }

    public function deleteForUser(User $user, string $conversationId): void
    {
        $conversation = Conversation::where('user_id', $user->id)->findOrFail($conversationId);
        $conversation->delete();
    }
}
