<?php

namespace App\Services;

class DatasetParsedPayloadBuilder
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $schema
     * @return array{metadata: array<string, mixed>, records: array<int, array<string, mixed>>}
     */
    public function build(string $sourceFilename, array $headers, array $records, array $schema): array
    {
        return [
            'metadata' => [
                'source' => $sourceFilename,
                'parsed_at' => now()->toIso8601String(),
                'total_records' => count($records),
                'headers' => $headers,
                'schema_inference' => $schema,
            ],
            'records' => $records,
        ];
    }
}
