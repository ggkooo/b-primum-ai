<?php

namespace App\Services;

use App\Models\DatasetRecord;
use Illuminate\Support\Collection;

class DatasetRecordSearchService
{
    public function __construct(private ?OllamaService $ollamaService = null)
    {
        $this->ollamaService ??= new OllamaService(new OllamaPromptBuilder());
    }

    /**
     * @return Collection<int, DatasetRecord>
     */
    public function search(?string $query = null, int $topK = 20, int $maxRecordsPerDataset = 50): Collection
    {
        $records = DatasetRecord::query()
            ->whereNotNull('semantic_description')
            ->where('semantic_description', '!=', '')
            ->orderBy('dataset_id')
            ->orderBy('record_index')
            ->get();

        if ($records->isEmpty()) {
            return collect();
        }

        if ($this->shouldUseEmbeddings($query)) {
            $rankedRecords = $this->searchByEmbedding($records, (string) $query, $topK);

            if ($rankedRecords->isNotEmpty()) {
                return $rankedRecords;
            }
        }

        return $records
            ->groupBy('dataset_id')
            ->flatMap(fn (Collection $group): Collection => $group->take($maxRecordsPerDataset))
            ->values();
    }

    private function shouldUseEmbeddings(?string $query): bool
    {
        return (bool) config('services.ollama.generate_embeddings', false)
            && is_string($query)
            && trim($query) !== '';
    }

    /**
     * @param Collection<int, DatasetRecord> $records
     * @return Collection<int, DatasetRecord>
     */
    private function searchByEmbedding(Collection $records, string $query, int $topK): Collection
    {
        $queryEmbedding = $this->ollamaService->generateEmbedding($query);

        if (!is_array($queryEmbedding) || $queryEmbedding === []) {
            return collect();
        }

        return $records
            ->filter(fn (DatasetRecord $record): bool => is_array($record->embedding) && $record->embedding !== [])
            ->map(fn (DatasetRecord $record): array => [
                'record' => $record,
                'score' => $this->cosineSimilarity($queryEmbedding, $record->embedding),
            ])
            ->sortByDesc('score')
            ->take($topK)
            ->pluck('record')
            ->values();
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