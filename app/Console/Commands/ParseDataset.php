<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ParseDataset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataset:parse {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse a CSV dataset into semantic JSON for AI';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\DatasetParserService $service)
    {
        $id = $this->argument('id');
        $dataset = \App\Models\Dataset::find($id);

        if (!$dataset) {
            $this->error("Dataset with ID {$id} not found.");
            return 1;
        }

        $this->info("Parsing dataset: {$dataset->original_filename}...");

        if ($service->parse($dataset)) {
            $this->info("Dataset parsed successfully!");
            $this->line("Parsed path: {$dataset->parsed_path}");
            return 0;
        }

        $this->error("Parsing failed.");
        return 1;
    }
}
