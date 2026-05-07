<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BatchProcessingService;
use Illuminate\Http\JsonResponse;

class ProcessBatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger the batch processing logic for pools.';

    protected $batchProcessingService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(BatchProcessingService $batchProcessingService)
    {
        parent::__construct();
        $this->batchProcessingService = $batchProcessingService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting batch processing...');

        try {
            $result = $this->batchProcessingService->processAllBetTiers();

            $httpCode = $result['http_code'] ?? 200;

            if ($httpCode >= 200 && $httpCode < 300) {
                $this->info("Batch processing completed. Status: {$result['status']}");
                $this->info("Message: " . ($result['message'] ?? 'No message'));
                if (isset($result['processed_count'])) {
                    $this->info("Processed Count: " . $result['processed_count']);
                }
                if (isset($result['current_tier'])) {
                    $this->info("Tier processed: " . $result['current_tier']);
                }
                return 0;
            } else {
                $this->error("Batch processing failed. Status: {$result['status']}");
                $this->error("Message: " . ($result['message'] ?? 'Unknown error'));
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("An exception occurred: " . $e->getMessage());
            return 1;
        }
    }
}
