<?php

namespace App\Services;

class DatasetCsvReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}|null
     */
    public function read(string $content): ?array
    {
        $lines = explode("\n", str_replace("\r", '', trim($content)));

        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv(array_shift($lines), ',', '"', '\\');
        $rows = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line, ',', '"', '\\');
            if (count($values) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, $values);
            if (!is_array($row)) {
                continue;
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
