<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;

class ChatService
{
    public function __construct(
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
        private readonly ConversationResolverService $conversationResolverService,
        private readonly ChatHistoryBuilderService $chatHistoryBuilderService,
    ) {
    }

    /**
     * @return array{
     *     conversation_id:string,
     *     response:string,
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>
     * }
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

        $history = $this->chatHistoryBuilderService->buildForConversation($conversation, 1);
        $clinicalResponse = $this->clinicalWorkflowService->generateResponse($conversation, $userMessage, $history);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => $clinicalResponse['response'],
        ]);

        return [
            'conversation_id' => $conversation->id,
            'response' => $clinicalResponse['response'],
            'stage' => $clinicalResponse['stage'],
            'summary' => $clinicalResponse['summary'],
            'missing_information' => $clinicalResponse['missing_information'],
            'follow_up_questions' => $clinicalResponse['follow_up_questions'],
            'diagnoses' => $clinicalResponse['diagnoses'],
        ];
    }
}
