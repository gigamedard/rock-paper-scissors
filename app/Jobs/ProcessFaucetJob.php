<?php

namespace App\Jobs;

use App\Helpers\Web3Helper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFaucetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $walletAddress;
    public float $amount;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $walletAddress, float $amount)
    {
        $this->userId = $userId;
        $this->walletAddress = $walletAddress;
        $this->amount = $amount;
    }

    /**
     * Execute the job.
     *
     * Le flag has_received_airdrop n'est posé qu'APRÈS un transfert on-chain
     * réussi : en cas d'échec (faucet vide, réseau indisponible…), l'utilisateur
     * peut retenter. Le tx hash est conservé pour l'audit.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning('[FAUCET] Job: utilisateur introuvable id ' . $this->userId);
            return;
        }

        if ($user->has_received_airdrop) {
            return; // déjà crédité (idempotence)
        }

        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $result = app(Web3Helper::class)->sendFaucet($nodeUrl, $this->walletAddress, $this->amount);

        $txHash = $result['txHash'] ?? null;
        if (!$txHash) {
            // Le transfert n'a pas produit de hash : on lève pour que le job
            // soit re-tenté (tries=3) sans bloquer le flag.
            throw new \RuntimeException('[FAUCET] Aucun txHash retourné par le bridge.');
        }

        $user->forceFill([
            'has_received_airdrop' => true,
            'airdrop_tx_hash' => $txHash,
        ])->save();

        Log::channel('business')->info('FAUCET_SENT', [
            'user' => $user->id,
            'wallet' => $this->walletAddress,
            'amount' => $this->amount,
            'tx' => $txHash,
        ]);
    }
}
