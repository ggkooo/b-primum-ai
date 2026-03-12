<?php

namespace Tests\Unit;

use App\Services\DatasetCsvReader;
use PHPUnit\Framework\TestCase;

class DatasetCsvReaderTest extends TestCase
{
    public function test_reads_valid_csv_content(): void
    {
        $reader = new DatasetCsvReader();
        $csv = "symptom1,symptom2,diagnosis\nyes,no,flu\nno,yes,cold";

        $result = $reader->read($csv);

        $this->assertNotNull($result);
        $this->assertSame(['symptom1', 'symptom2', 'diagnosis'], $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('flu', $result['rows'][0]['diagnosis']);
    }

    public function test_returns_null_for_invalid_csv_with_no_data_rows(): void
    {
        $reader = new DatasetCsvReader();

        $result = $reader->read('header_only');

        $this->assertNull($result);
    }

    public function test_skips_rows_with_invalid_column_count(): void
    {
        $reader = new DatasetCsvReader();
        $csv = "a,b,c\n1,2\n3,4,5";

        $result = $reader->read($csv);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('5', $result['rows'][0]['c']);
    }
}
