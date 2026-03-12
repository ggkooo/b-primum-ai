<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;

class ChatHistoryBuilderService
{
    /**
     * @return array<int, array{role:string,parts:array<int, array{text:string}>}>
     */
    public function buildForConversation(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->role,
                'parts' => [['text' => $message->content]],
            ])
            ->toArray();
    }
}
