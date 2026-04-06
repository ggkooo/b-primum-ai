<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;

class ConversationResolverService
{
    public function resolve(User $user, string $userMessage, ?string $conversationId): Conversation
    {
        if ($conversationId) {
            return Conversation::where('user_id', $user->id)->findOrFail($conversationId);
        }

        return Conversation::create([
            'user_id' => $user->id,
            'title' => $this->makeTitle($userMessage),
            'last_message_at' => now(),
            'clinical_stage' => 'anamnesis',
        ]);
    }

    private function makeTitle(string $userMessage): string
    {
        $title = trim($userMessage);

        if ($title === '') {
            return 'Nova conversa clinica';
        }

        $title = mb_substr($title, 0, 50);

        return $title . '...';
    }
}
