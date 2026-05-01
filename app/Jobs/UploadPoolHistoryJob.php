<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UploadPoolHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $poolId;
    public $data;

    /**
     * Create a new job instance.
     */
    public function __construct($poolId, $data)
    {
        $this->poolId = $poolId;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\IpfsService $ipfsService, \App\Helpers\Web3Helper $web3Helper): void
    {
        try {
            $cid = $ipfsService->uploadJson($this->data);
            if (!$cid) {
                \Illuminate\Support\Facades\Log::warning("UploadPoolHistoryJob: Failed to send pool {$this->poolId} to IPFS.");
                return;
            }
            $nodeUrl = env('NODE_URL');
            $web3Helper->sendPoolCIDToSmartContract($nodeUrl, $cid, $this->poolId);
            \Illuminate\Support\Facades\Log::info("UploadPoolHistoryJob: Successfully uploaded and sent CID ($cid) to smart contract for pool {$this->poolId}.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("UploadPoolHistoryJob Error for pool {$this->poolId}: " . $e->getMessage());
        }
    }
}
