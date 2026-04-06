<?php

namespace App\Services;

use App\Models\DatasetRecord;
use Illuminate\Support\Collection;

class DatasetRecordSearchService
{
    private const MINIMUM_TOKEN_LENGTH = 3;

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

        if (is_string($query) && trim($query) !== '') {
            $lexicallyRankedRecords = $this->searchByLexicalSimilarity($records, $query, $topK);

            if ($lexicallyRankedRecords->isNotEmpty()) {
                return $lexicallyRankedRecords;
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
     * @param Collection<int, DatasetRecord> $records
     * @return Collection<int, DatasetRecord>
     */
    private function searchByLexicalSimilarity(Collection $records, string $query, int $topK): Collection
    {
        $queryTerms = $this->extractTerms($query);

        if ($queryTerms === []) {
            return collect();
        }

        return $records
            ->map(function (DatasetRecord $record) use ($queryTerms): array {
                $description = (string) $record->semantic_description;
                $score = $this->calculateLexicalScore($description, $queryTerms);

                return [
                    'record' => $record,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($topK)
            ->pluck('record')
            ->values();
    }

    /**
     * @param array<int, string> $queryTerms
     */
    private function calculateLexicalScore(string $description, array $queryTerms): int
    {
        $normalizedDescription = $this->normalizeText($description);
        $score = 0;

        foreach ($queryTerms as $term) {
            if (str_contains($normalizedDescription, $term)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $parts = preg_split('/\s+/', $normalized) ?: [];

        $terms = array_filter($parts, fn (string $term): bool => mb_strlen($term) >= self::MINIMUM_TOKEN_LENGTH);

        return array_values(array_unique($terms));
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
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