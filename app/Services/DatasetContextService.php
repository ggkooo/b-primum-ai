<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;

class DatasetContextService
{
    /**
     * Aggregate semantic descriptions from all parsed datasets.
     */
    public function buildContext(int $maxRecordsPerDataset = 50): string
    {
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
}
