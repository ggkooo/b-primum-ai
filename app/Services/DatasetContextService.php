<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;

class DatasetContextService
{
    public function __construct(private ?DatasetRecordSearchService $datasetRecordSearchService = null)
    {
        $this->datasetRecordSearchService ??= new DatasetRecordSearchService();
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
        $records = $this->datasetRecordSearchService->search($query, $topK, $maxRecordsPerDataset);

        if ($records->isEmpty()) {
            return '';
        }

        return $records
            ->map(function ($record, int $index): string {
                $datasetId = (string) $record->dataset_id;
                $recordIndex = (int) $record->record_index;
                $description = trim((string) $record->semantic_description);

                return '[' . ($index + 1) . '][dataset:' . $datasetId . '][record:' . $recordIndex . '] ' . $description;
            })
            ->implode("\n");
    }
}
