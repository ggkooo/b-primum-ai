<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;

class ChatService
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly DatasetContextService $datasetContextService,
        private readonly ConversationResolverService $conversationResolverService,
        private readonly ChatHistoryBuilderService $chatHistoryBuilderService,
    ) {
    }

    /**
     * Process a user message and return conversation metadata.
     *
     * @return array{conversation_id:string,response:string|null}
     */
    public function handleMessage(User $user, string $userMessage, ?string $conversationId): array
    {
        $conversation = $this->conversationResolverService->resolve($user, $userMessage, $conversationId);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $history = $this->chatHistoryBuilderService->buildForConversation($conversation);

        $context = $this->datasetContextService->buildContext();
        $aiResponse = $this->geminiService->generateResponse($userMessage, $history, $context);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => $aiResponse,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return [
            'conversation_id' => $conversation->id,
            'response' => $aiResponse,
        ];
    }
}
