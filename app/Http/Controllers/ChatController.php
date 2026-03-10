<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Handle chat communication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        set_time_limit(0); // Remove execution time limit for AI response
        $request->validate([
            'message' => 'required|string',
            'history' => 'nullable|array',
        ]);

        $userMessage = $request->input('message');
        $history = $request->input('history', []);

        // Retrieve RAG Context from parsed datasets
        $context = $this->getParsedDatasetsContext();

        // Generate AI Response
        $aiResponse = $this->geminiService->generateResponse($userMessage, $history, $context);

        return response()->json([
            'status' => 'success',
            'data' => [
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
