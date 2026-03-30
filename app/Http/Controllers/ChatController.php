<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Http\Requests\ChatRequest;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function chat(ChatRequest $request)
    {
        set_time_limit(0); // Remove execution time limit for AI response
        try {
            $result = $this->chatService->handleMessage(
                $request->user(),
                (string) $request->input('message'),
                $request->input('conversation_id'),
            );

            return $this->success([
                'conversation_id' => $result['conversation_id'],
                'response' => $result['response'],
            ]);
        } catch (AiProviderException $e) {
            return $this->error($e->getMessage(), $e->httpStatus());
        } catch (ModelNotFoundException $e) {
            return $this->error('Conversa nao encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Unexpected chat controller exception', [
                'message' => $e->getMessage(),
            ]);

            return $this->error('Falha interna ao processar a conversa.', 500);
        }
    }
}
