<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\Dataset;
use App\Services\DatasetParserService;

class ParseDatasetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 7200;

    /**
     * Create a new job instance.
     */
    public function __construct(public Dataset $dataset)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DatasetParserService $service): void
    {
        $service->parse($this->dataset);
    }
}
