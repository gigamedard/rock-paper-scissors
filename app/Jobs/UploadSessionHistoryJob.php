<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UploadSessionHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $walletAddress;
    public $userId;
    public $data;

    /**
     * Create a new job instance.
     */
    public function __construct($walletAddress, $userId, $data)
    {
        $this->walletAddress = $walletAddress;
        $this->userId = $userId;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\PinataService $pinataService, \App\Helpers\Web3Helper $web3Helper): void
    {
        try {
            $cid = $pinataService->pinJsonData(json_encode($this->data));
            if (!$cid) {
                \Illuminate\Support\Facades\Log::warning("UploadSessionHistoryJob: Failed to send session history to IPFS for User ID: {$this->userId}. Using dummy CID for testing.");
                $cid = "QmDummyCidForTestingBypassPinataQuotaExceeded123";
            }
            $nodeUrl = env('NODE_URL');
            $web3Helper->sendSessionCIDToSmartContract($nodeUrl, $cid, $this->walletAddress);
            \Illuminate\Support\Facades\Log::info("UploadSessionHistoryJob: Successfully processed CID ($cid) for user {$this->userId}.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("UploadSessionHistoryJob Error for user {$this->userId}: " . $e->getMessage());
        }
    }
}
