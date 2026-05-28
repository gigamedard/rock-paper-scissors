<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $offerParams;

    /**
     * Create a new job instance.
     */
    public function __construct(array $offerParams)
    {
        $this->offerParams = $offerParams;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nodeUrl = env('NODE_URL', 'http://127.0.0.1:3000');
        
        try {
            $response = Http::post("{$nodeUrl}/create-offer", $this->offerParams);

            if ($response->successful()) {
                Log::info('Create offer completed successfully.', ['response' => $response->json()]);
            } else {
                Log::error('Create offer failed.', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Create offer failed with exception: ' . $e->getMessage());
        }
    }
}
