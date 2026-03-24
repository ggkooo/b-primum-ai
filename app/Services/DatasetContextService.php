<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;

class DatasetContextService
{
    /**
     * Aggregate and summarize parsed datasets into a compact clinical context.
     */
    public function buildContext(int $maxRecordsPerDataset = 50): string
    {
        $datasets = Dataset::whereNotNull('parsed_path')->get();
        $aggregatedContext = [];

        foreach ($datasets as $dataset) {
            if (!Storage::disk('local')->exists($dataset->parsed_path)) {
                continue;
            }

            $content = json_decode(Storage::disk('local')->get($dataset->parsed_path), true);

            if (!isset($content['records']) || !is_array($content['records'])) {
                continue;
            }

            $records = array_slice($content['records'], 0, $maxRecordsPerDataset);
            $metadata = is_array($content['metadata'] ?? null) ? $content['metadata'] : [];

            $datasetBlock = $this->buildDatasetBlock(
                source: (string) ($metadata['source'] ?? $dataset->original_filename),
                metadata: $metadata,
                records: $records,
            );

            if ($datasetBlock !== '') {
                $aggregatedContext[] = $datasetBlock;
            }
        }

        return implode("\n\n", $aggregatedContext);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $records
     */
    private function buildDatasetBlock(string $source, array $metadata, array $records): string
    {
        $lines = [];
        $lines[] = "Dataset: {$source}";

        $schema = is_array($metadata['schema_inference'] ?? null)
            ? $metadata['schema_inference']
            : [];

        $target = is_string($schema['target'] ?? null) ? $schema['target'] : null;
        $features = is_array($schema['features'] ?? null) ? $schema['features'] : [];

        if ($target !== null && $target !== '') {
            $lines[] = "Target clínico principal: {$target}";
        }

        if ($features !== []) {
            $lines[] = 'Variáveis observadas: ' . implode(', ', array_slice($features, 0, 12));
        }

        $targetDistribution = $this->summarizeTargetDistribution($records, $target);

        if ($targetDistribution !== []) {
            $lines[] = 'Distribuição de classes (amostra): ' . implode(', ', $targetDistribution);
        }

        $semanticExamples = $this->extractSemanticExamples($records, 6);

        if ($semanticExamples !== []) {
            $lines[] = 'Casos clínicos resumidos:';

            foreach ($semanticExamples as $example) {
                $lines[] = '- ' . $example;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, string>
     */
    private function summarizeTargetDistribution(array $records, ?string $target): array
    {
        if ($target === null || $target === '') {
            return [];
        }

        $counts = [];

        foreach ($records as $record) {
            $original = is_array($record['original'] ?? null) ? $record['original'] : [];

            if (!array_key_exists($target, $original)) {
                continue;
            }

            $label = trim((string) $original[$target]);

            if ($label === '') {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        if ($counts === []) {
            return [];
        }

        arsort($counts);

        $summary = [];

        foreach (array_slice($counts, 0, 8, true) as $label => $count) {
            $summary[] = "{$label}: {$count}";
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, string>
     */
    private function extractSemanticExamples(array $records, int $maxExamples): array
    {
        $examples = [];

        foreach ($records as $record) {
            $description = trim((string) ($record['semantic_description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $examples[] = $description;

            if (count($examples) >= $maxExamples) {
                break;
            }
        }

        return $examples;
    }
}
