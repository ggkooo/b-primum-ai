<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;

class ChatService
{
    public function __construct(
        private readonly OllamaService $ollamaService,
        private readonly ConversationResolverService $conversationResolverService,
        private readonly ChatHistoryBuilderService $chatHistoryBuilderService,
        private readonly DatasetContextService $datasetContextService,
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

        $conversation->update(['last_message_at' => now()]);

        $history = $this->chatHistoryBuilderService->buildForConversation($conversation);
        $datasetContext = $this->datasetContextService->buildContext(30, $userMessage, 25);
        $aiResponse = $this->ollamaService->generateResponse($userMessage, $history, $datasetContext);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => $aiResponse,
        ]);

        return [
            'conversation_id' => $conversation->id,
            'response' => $aiResponse,
        ];
    }
}
