<?php

namespace App\Jobs;

use App\Helpers\Web3Helper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconstructPoolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $invalidAddresses;
    public float $baseBetEther;

    /**
     * Create a new job instance.
     */
    public function __construct(array $invalidAddresses, float $baseBetEther)
    {
        $this->invalidAddresses = $invalidAddresses;
        $this->baseBetEther = $baseBetEther;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $web3 = app(Web3Helper::class);
        if (!empty($this->invalidAddresses)) {
            $web3->refundUsers($nodeUrl, $this->invalidAddresses);
            $web3->invalidatePoolUsers($nodeUrl, $this->baseBetEther, $this->invalidAddresses);
        } else {
            $web3->validatePool($nodeUrl, $this->baseBetEther);
        }
    }
}
