<?php

namespace App\Services;

class DatasetSemanticDescriptionService
{
    public function inferSchema(array $headers): array
    {
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

    public function generateSemanticDescription(array $row, array $schema): string
    {
        $description = 'This record describes an instance where: ';
        $features = [];

        foreach ($schema['features'] as $feature) {
            if (in_array($feature, $schema['identifiers'])) {
                continue;
            }

            $value = $row[$feature];
            $features[] = "the '{$feature}' is '{$value}'";
        }

        $description .= implode(', ', $features);
        $description .= ". The resulting '{$schema['target']}' is '{$row[$schema['target']]}'" . '.';

        return $description;
    }
}
