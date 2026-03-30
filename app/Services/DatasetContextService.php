<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetRecord;
use Illuminate\Support\Facades\Storage;

class DatasetContextService
{
    public function __construct(private ?OllamaService $ollamaService = null)
    {
        $this->ollamaService ??= new OllamaService(new OllamaPromptBuilder());
    }

    /**
     * Aggregate semantic descriptions from all parsed datasets.
     */
    public function buildContext(int $maxRecordsPerDataset = 50, ?string $query = null, int $topK = 20): string
    {
        $fromDatabase = $this->buildContextFromDatabase($maxRecordsPerDataset, $query, $topK);

        if ($fromDatabase !== '') {
            return $fromDatabase;
        }

        $datasets = Dataset::whereNotNull('parsed_path')->get();
        $aggregatedContext = '';

        foreach ($datasets as $dataset) {
            if (!Storage::disk('local')->exists($dataset->parsed_path)) {
                continue;
            }

            $content = json_decode(Storage::disk('local')->get($dataset->parsed_path), true);

            if (!isset($content['records']) || !is_array($content['records'])) {
                continue;
            }

            $records = array_slice($content['records'], 0, $maxRecordsPerDataset);

            foreach ($records as $record) {
                if (!isset($record['semantic_description'])) {
                    continue;
                }

                $aggregatedContext .= $record['semantic_description'] . "\n";
            }
        }

        return $aggregatedContext;
    }

    private function buildContextFromDatabase(int $maxRecordsPerDataset, ?string $query, int $topK): string
    {
        $records = DatasetRecord::query()
            ->whereNotNull('semantic_description')
            ->where('semantic_description', '!=', '')
            ->orderBy('dataset_id')
            ->orderBy('record_index')
            ->get();

        if ($records->isEmpty()) {
            return '';
        }

        if ((bool) config('services.ollama.generate_embeddings', false) && is_string($query) && trim($query) !== '') {
            $queryEmbedding = $this->ollamaService->generateEmbedding($query);

            if (is_array($queryEmbedding) && $queryEmbedding !== []) {
                $scored = $records
                    ->filter(fn (DatasetRecord $record): bool => is_array($record->embedding) && $record->embedding !== [])
                    ->map(function (DatasetRecord $record) use ($queryEmbedding): array {
                        return [
                            'score' => $this->cosineSimilarity($queryEmbedding, $record->embedding),
                            'text' => (string) $record->semantic_description,
                        ];
                    })
                    ->sortByDesc('score')
                    ->take($topK)
                    ->pluck('text')
                    ->implode("\n");

                if ($scored !== '') {
                    return $scored;
                }
            }
        }

        $grouped = $records->groupBy('dataset_id');
        $lines = [];

        foreach ($grouped as $datasetRecords) {
            foreach ($datasetRecords->take($maxRecordsPerDataset) as $record) {
                $lines[] = (string) $record->semantic_description;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $size = min(count($a), count($b));

        if ($size === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $size; $i++) {
            $ai = (float) $a[$i];
            $bi = (float) $b[$i];

            $dot += $ai * $bi;
            $normA += $ai * $ai;
            $normB += $bi * $bi;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
