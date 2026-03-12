<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Services\ChatService;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function chat(ChatRequest $request)
    {
        set_time_limit(0); // Remove execution time limit for AI response
        $result = $this->chatService->handleMessage(
            $request->user(),
            (string) $request->input('message'),
            $request->input('conversation_id'),
        );

        return $this->success([
            'conversation_id' => $result['conversation_id'],
            'response' => $result['response'],
        ]);
    }
}
