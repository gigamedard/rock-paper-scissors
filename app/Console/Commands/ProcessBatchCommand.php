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
            /** @var JsonResponse $response */
            $response = $this->batchProcessingService->processBatch();
            
            $status = $response->getStatusCode();
            $content = $response->getData(true); // Get content as array

            if ($status >= 200 && $status < 300) {
                $this->info("Batch processing completed successfully. Status: {$status}");
                $this->info("Message: " . ($content['message'] ?? 'No message'));
                if (isset($content['processed_count'])) {
                    $this->info("Processed Count: " . $content['processed_count']);
                }
                return 0;
            } else {
                $this->error("Batch processing failed. Status: {$status}");
                $this->error("Message: " . ($content['message'] ?? 'Unknown error'));
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("An exception occurred: " . $e->getMessage());
            return 1;
        }
    }
}
