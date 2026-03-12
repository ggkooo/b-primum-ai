<?php

namespace Tests\Unit;

use App\Services\DatasetParsedPayloadBuilder;
use PHPUnit\Framework\TestCase;

class DatasetParsedPayloadBuilderTest extends TestCase
{
    public function test_build_returns_expected_metadata_and_records(): void
    {
        $builder = new DatasetParsedPayloadBuilder();
        $headers = ['symptom', 'diagnosis'];
        $records = [
            [
                'original' => ['symptom' => 'fever', 'diagnosis' => 'flu'],
                'semantic_description' => 'desc',
            ],
        ];
        $schema = [
            'target' => 'diagnosis',
            'features' => ['symptom'],
            'identifiers' => [],
        ];

        $result = $builder->build('dataset.csv', $headers, $records, $schema);

        $this->assertSame('dataset.csv', $result['metadata']['source']);
        $this->assertSame(1, $result['metadata']['total_records']);
        $this->assertSame($headers, $result['metadata']['headers']);
        $this->assertSame($schema, $result['metadata']['schema_inference']);
        $this->assertArrayHasKey('parsed_at', $result['metadata']);
        $this->assertSame($records, $result['records']);
    }
}
