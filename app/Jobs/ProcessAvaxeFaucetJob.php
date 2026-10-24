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

/**
 * Faucet AVAXe (ERC-20) — chantier feat/avaxe-bridge (P2).
 * Envoie des AVAXe de test depuis le portefeuille faucet du bridge vers le
 * wallet du joueur. Le flag has_received_avaxe_airdrop n'est posé qu'APRÈS un
 * transfert on-chain réussi (idempotence, retry possible en cas d'échec).
 */
class ProcessAvaxeFaucetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $walletAddress;
    public float $amount;

    public function __construct(int $userId, string $walletAddress, float $amount)
    {
        $this->userId = $userId;
        $this->walletAddress = $walletAddress;
        $this->amount = $amount;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning('[AVAXE-FAUCET] Job: utilisateur introuvable id ' . $this->userId);
            return;
        }

        if ($user->has_received_avaxe_airdrop) {
            return; // déjà crédité (idempotence)
        }

        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $result = app(Web3Helper::class)->sendAvaxeFaucet($nodeUrl, $this->walletAddress, $this->amount);

        $txHash = $result['txHash'] ?? null;
        if (!$txHash) {
            throw new \RuntimeException('[AVAXE-FAUCET] Aucun txHash retourné par le bridge.');
        }

        $user->forceFill([
            'has_received_avaxe_airdrop' => true,
            'avaxe_airdrop_tx_hash' => $txHash,
        ])->save();

        Log::channel('business')->info('AVAXE_FAUCET_SENT', [
            'user' => $user->id,
            'wallet' => $this->walletAddress,
            'amount' => $this->amount,
            'tx' => $txHash,
        ]);
    }
}
