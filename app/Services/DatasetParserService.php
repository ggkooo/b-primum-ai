<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatasetParserService
{
    public function __construct(
        private ?DatasetCsvReader $csvReader = null,
        private ?DatasetSemanticDescriptionService $semanticDescriptionService = null,
        private ?DatasetParsedPayloadBuilder $payloadBuilder = null,
    ) {
        // Keep backward compatibility for manual `new DatasetParserService()` usage.
        $this->csvReader ??= new DatasetCsvReader();
        $this->semanticDescriptionService ??= new DatasetSemanticDescriptionService();
        $this->payloadBuilder ??= new DatasetParsedPayloadBuilder();
    }

    /**
     * Parse a CSV dataset into a semantic JSON structure.
     *
     * @param  \App\Models\Dataset  $dataset
     * @return bool
     */
    public function parse(Dataset $dataset): bool
    {
        if (!Storage::exists($dataset->storage_path)) {
            return false;
        }

        $content = Storage::get($dataset->storage_path);
        $parsedCsv = $this->csvReader->read($content);

        if ($parsedCsv === null) {
            return false;
        }

        $headers = $parsedCsv['headers'];
        $rows = $parsedCsv['rows'];
        $records = [];
        $schema = $this->semanticDescriptionService->inferSchema($headers);

        foreach ($rows as $row) {
            $semanticDescription = $this->semanticDescriptionService->generateSemanticDescription($row, $schema);

            $records[] = [
                'original' => $row,
                'semantic_description' => $semanticDescription,
            ];
        }

        $parsedData = $this->payloadBuilder->build(
            $dataset->original_filename,
            $headers,
            $records,
            $schema,
        );

        $parsedFilename = 'parsed/' . Str::random(40) . '.json';
        Storage::put($parsedFilename, json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $dataset->update([
            'parsed_path' => $parsedFilename,
            'metadata' => $parsedData['metadata'],
        ]);

        return true;
    }
}
