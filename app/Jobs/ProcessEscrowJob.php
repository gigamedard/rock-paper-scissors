<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessEscrowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $operation;
    public array $params;

    /**
     * Create a new job instance.
     */
    public function __construct(string $operation, array $params)
    {
        $this->operation = $operation;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nodeUrl = env('NODE_URL', 'http://127.0.0.1:3000');
        $endpoint = "/escrow/{$this->operation}";
        
        try {
            $response = Http::post($nodeUrl . $endpoint, $this->params);

            if ($response->successful()) {
                Log::info("Escrow operation {$this->operation} completed successfully.", ['response' => $response->json()]);
                
                // Clear trades cache
                $this->clearTradesCache();
            } else {
                Log::error("Escrow operation {$this->operation} failed.", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Escrow operation {$this->operation} failed with exception: " . $e->getMessage());
        }
    }

    private function clearTradesCache(): void
    {
        $keys = ['escrow_stats'];
        for ($start = 0; $start <= 2000; $start += 20) {
            $keys[] = "escrow_trades_{$start}_20";
        }
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
