<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatasetParserService
{
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
        $lines = explode("\n", str_replace("\r", "", trim($content)));
        
        if (count($lines) < 2) {
            return false;
        }

        $headers = str_getcsv(array_shift($lines));
        $records = [];
        $schema = $this->extractSchema($headers);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $values = str_getcsv($line);
            if (count($values) !== count($headers)) continue;

            $row = array_combine($headers, $values);
            $semanticDescription = $this->generateSemanticDescription($row, $schema);

            $records[] = [
                'original' => $row,
                'semantic_description' => $semanticDescription,
            ];
        }

        $parsedData = [
            'metadata' => [
                'source' => $dataset->original_filename,
                'parsed_at' => now()->toIso8601String(),
                'total_records' => count($records),
                'headers' => $headers,
                'schema_inference' => $schema,
            ],
            'records' => $records,
        ];

        $parsedFilename = 'parsed/' . Str::random(40) . '.json';
        Storage::put($parsedFilename, json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $dataset->update([
            'parsed_path' => $parsedFilename,
            'metadata' => $parsedData['metadata'],
        ]);

        return true;
    }

    /**
     * Infer the schema and column roles.
     */
    protected function extractSchema(array $headers): array
    {
        // Simple heuristic: 
        // - Last column often is the "Target" (Diagnosis, Outcome, etc)
        // - ID column if name matches
        // - Rest are features/symptoms
        
        $schema = [
            'target' => end($headers),
            'features' => array_slice($headers, 0, -1),
            'identifiers' => [],
        ];

        foreach ($headers as $header) {
            if (preg_match('/(id|uuid|code)/i', $header)) {
                $schema['identifiers'][] = $header;
            }
        }

        return $schema;
    }

    /**
     * Generate a natural language description of the data row.
     */
    protected function generateSemanticDescription(array $row, array $schema): string
    {
        $description = "This record describes an instance where: ";
        
        $features = [];
        foreach ($schema['features'] as $feature) {
            if (in_array($feature, $schema['identifiers'])) continue;
            
            $value = $row[$feature];
            $features[] = "the '{$feature}' is '{$value}'";
        }

        $description .= implode(", ", $features);
        $description .= ". The resulting '{$schema['target']}' is '{$row[$schema['target']]}'.";

        return $description;
    }
}
