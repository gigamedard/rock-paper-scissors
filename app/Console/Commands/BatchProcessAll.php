<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BatchProcessingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BatchProcessAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:process-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process batches for all configured base bet amounts.';

    protected $batchService;

    public function __construct(BatchProcessingService $batchService)
    {
        parent::__construct();
        $this->batchService = $batchService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseBets = Config::get('pool.base_bet', []);

        if (empty($baseBets)) {
            $this->error("No base_bet configured in pool.base_bet.");
            return 1;
        }

        $this->info("Starting batch processing for " . count($baseBets) . " base bet amounts...");

        foreach ($baseBets as $baseBet) {
            $this->info("Processing base bet: {$baseBet}");
            try {
                // Call the service
                $result = $this->batchService->processBatch((float)$baseBet);
                
                $status = $result['status'] ?? 'unknown';
                $msg = $result['message'] ?? 'No message';
                $httpCode = $result['http_code'] ?? 200;

                if ($httpCode >= 400) {
                    $this->error("Error for base bet {$baseBet}: [{$status}] {$msg}");
                } else {
                    $this->info("Success for base bet {$baseBet}: [{$status}] {$msg}");
                }

            } catch (\Exception $e) {
                $this->error("Exception processing base bet {$baseBet}: " . $e->getMessage());
                Log::error("BatchProcessAll: Exception for base bet {$baseBet}: " . $e->getMessage());
            }
        }

        $this->info("Batch processing cycle completed.");
        return 0;
    }
}
