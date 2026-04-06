<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;

class ChatHistoryBuilderService
{
    /**
     * @return array<int, array{role:string,content:string}>
     */
    public function buildForConversation(Conversation $conversation, int $excludeRecentMessages = 0): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        if ($excludeRecentMessages > 0) {
            $messages = $messages->slice(0, max(0, $messages->count() - $excludeRecentMessages));
        }

        return $messages
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->role === 'model' ? 'assistant' : $message->role,
                'content' => $message->content,
            ])
            ->toArray();
    }
}
