<?php

namespace App\Jobs;

use App\Helpers\Web3Helper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $walletAddress;
    public float $amount;

    /**
     * Create a new job instance.
     */
    public function __construct(string $walletAddress, float $amount)
    {
        $this->walletAddress = $walletAddress;
        $this->amount = $amount;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        app(Web3Helper::class)->sendPayement($nodeUrl, $this->walletAddress, $this->amount);
    }
}
