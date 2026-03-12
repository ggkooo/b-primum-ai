<?php

namespace App\Http\Controllers;

use App\Services\ConversationService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversationService)
    {
    }

    public function index(Request $request)
    {
        $conversations = $this->conversationService->listForUser($request->user());

        return $this->success([
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $conversation = $this->conversationService->findForUser($request->user(), $id);

        return $this->success([
            'conversation' => $conversation,
        ]);
    }
}
