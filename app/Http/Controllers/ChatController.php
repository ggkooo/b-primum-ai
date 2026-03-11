<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Dataset;

class ChatController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function index(Request $request)
    {
        $conversations = $request->user()->conversations()->orderBy('last_message_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversations' => $conversations,
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $conversation = Conversation::with(['messages' => function($query) {
            $query->orderBy('created_at', 'asc');
        }])->where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation' => $conversation,
            ],
        ]);
    }

    public function chat(Request $request)
    {
        set_time_limit(0); // Remove execution time limit for AI response
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $userMessage = $request->input('message');
        $conversationId = $request->input('conversation_id');
        $user = $request->user();

        // Find or create conversation
        if ($conversationId) {
            $conversation = Conversation::where('user_id', $user->id)->findOrFail($conversationId);
        } else {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'title' => substr($userMessage, 0, 50) . '...',
                'last_message_at' => now(),
            ]);
        }

        // Save user message
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Get History from DB for Gemini
        $history = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'parts' => [['text' => $msg->content]]
                ];
            })->toArray();

        // Retrieve RAG Context from parsed datasets
        $context = $this->getParsedDatasetsContext();

        // Generate AI Response
        $aiResponse = $this->geminiService->generateResponse($userMessage, $history, $context);

        // Save AI message
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'model',
            'content' => $aiResponse,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'response' => $aiResponse,
            ],
        ]);
    }

    /**
     * Aggregate semantic descriptions from all parsed datasets.
     *
     * @return string
     */
    private function getParsedDatasetsContext(): string
    {
        $datasets = Dataset::whereNotNull('parsed_path')->get();
        $aggregatedContext = "";

        foreach ($datasets as $dataset) {
            if (Storage::disk('local')->exists($dataset->parsed_path)) {
                $content = json_decode(Storage::disk('local')->get($dataset->parsed_path), true);
                
                if (isset($content['records'])) {
                    // Limiting to first 50 records for context to avoid prompt size issues and focus on quality
                    $records = array_slice($content['records'], 0, 50);
                    foreach ($records as $record) {
                        $aggregatedContext .= $record['semantic_description'] . "\n";
                    }
                }
            }
        }

        return $aggregatedContext;
    }
}
