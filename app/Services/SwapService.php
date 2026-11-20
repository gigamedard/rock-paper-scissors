<?php

namespace App\Services;

use App\Models\SwapOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SwapService
{
    private const TX_REGEX = '/^(0x)?[a-f0-9]{64}$/i';

    public function config(): array
    {
        return [
            'enabled' => (bool) config('swap.enabled', true),
            'rate' => (float) config('swap.rate', 7.13),
            'fee_percent' => (float) config('swap.fee_percent', 0),
            'min_usdt' => (float) config('swap.min_usdt', 0.5),
            'max_usdt' => (float) config('swap.max_usdt', 10),
            'min_avaxe' => (float) config('swap.min_avaxe', 0.1),
            'max_avaxe' => (float) config('swap.max_avaxe', 1.4025),
            'usdt_treasury_address' => (string) config('swap.usdt_treasury_address'),
            'avaxe_hot_address' => (string) config('swap.avaxe_hot_address'),
            'avaxe_contract' => (string) config('swap.avaxe_contract'),
            'avaxe_decimals' => (int) config('swap.avaxe_decimals', 18),
            'pingala_rpc' => (string) config('swap.pingala_rpc'),
            'networks' => collect(config('swap.networks', []))->map(fn ($n, $k) => [
                'key' => $k, 'label' => $n['label'], 'native' => $n['native'],
                'chain_id' => (int) ($n['chain_id'] ?? 0), 'usdt_contract' => (string) ($n['usdt'] ?? ''), 'usdt_decimals' => (int) ($n['usdt_decimals'] ?? 6),
                'rpc' => (string) ($n['rpc'] ?? ''),
            ])->values()->all(),
        ];
    }

    public function createUsdtToAvaxe(string $network, float $amountUsdt, string $payoutAddress): SwapOrder
    {
        $this->guard($network);
        $min = (float) config('swap.min_usdt', 0.5);
        $max = (float) config('swap.max_usdt', 10);
        if ($amountUsdt < $min - 1e-9) throw ValidationException::withMessages(['amount' => "Minimum {$min} USDT."]);
        if ($amountUsdt > $max + 1e-9) throw ValidationException::withMessages(['amount' => "Maximum {$max} USDT."]);
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $payoutAddress)) throw ValidationException::withMessages(['payout_address' => 'Adresse AVAXe invalide.']);
        $rate = (float) config('swap.rate', 7.13);
        $amountOut = round($amountUsdt / $rate, 8);
        $deposit = (string) config('swap.usdt_treasury_address');
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $deposit)) throw ValidationException::withMessages(['swap' => 'Adresse USDT non configurée.']);
        return $this->create(SwapOrder::USDT_TO_AVAXE, $network, $amountUsdt, $amountOut, $rate, $deposit, $payoutAddress);
    }

    public function createAvaxeToUsdt(string $network, float $amountAvaxe, string $payoutAddress): SwapOrder
    {
        $this->guard($network);
        $min = (float) config('swap.min_avaxe', 0.1);
        $max = (float) config('swap.max_avaxe', 1.4025);
        if ($amountAvaxe < $min - 1e-9) throw ValidationException::withMessages(['amount' => "Minimum {$min} AVAXe."]);
        if ($amountAvaxe > $max + 1e-9) throw ValidationException::withMessages(['amount' => "Maximum {$max} AVAXe (max 10 USDT)."]);
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $payoutAddress)) throw ValidationException::withMessages(['payout_address' => 'Adresse USDT invalide.']);
        $rate = (float) config('swap.rate', 7.13);
        $amountOut = round($amountAvaxe * $rate, 8);
        if ($amountOut > (float) config('swap.max_usdt', 10) + 1e-9) throw ValidationException::withMessages(['amount' => 'Montant USDT de sortie trop élevé.']);
        $deposit = (string) config('swap.avaxe_hot_address');
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $deposit)) throw ValidationException::withMessages(['swap' => 'Adresse AVAXe swap non configurée.']);
        return $this->create(SwapOrder::AVAXE_TO_USDT, $network, $amountAvaxe, $amountOut, $rate, $deposit, $payoutAddress);
    }

    public function submitDeposit(SwapOrder $order, string $txHash): SwapOrder
    {
        $txHash = strtolower(trim($txHash));
        if (! preg_match(self::TX_REGEX, $txHash)) throw ValidationException::withMessages(['tx_hash' => 'Hash de transaction invalide.']);
        return DB::transaction(function () use ($order, $txHash) {
            $locked = SwapOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== SwapOrder::PENDING_DEPOSIT) throw ValidationException::withMessages(['order' => 'Cette commande n’attend pas de dépôt.']);
            if (SwapOrder::where('deposit_tx_hash', $txHash)->whereKeyNot($locked->id)->exists()) throw ValidationException::withMessages(['tx_hash' => 'Ce hash est déjà utilisé.']);
            $locked->update(['deposit_tx_hash' => $txHash, 'status' => SwapOrder::PENDING_VERIFICATION]);
            return $locked;
        });
    }

    public function find(string $reference): ?SwapOrder
    {
        return SwapOrder::where('reference', strtoupper(trim($reference)))->first();
    }

    private function create(string $direction, string $network, float $amountIn, float $amountOut, float $rate, string $deposit, string $payout): SwapOrder
    {
        do { $ref = 'SWAP-' . now()->format('Ymd') . '-' . $this->randomCode(7); } while (SwapOrder::where('reference', $ref)->exists());
        return SwapOrder::create([
            'reference' => $ref, 'direction' => $direction, 'network' => $network,
            'amount_in' => $amountIn, 'amount_out' => $amountOut, 'rate' => $rate,
            'deposit_address' => $deposit, 'payout_address' => $payout,
            'status' => SwapOrder::PENDING_DEPOSIT,
        ]);
    }

    private function guard(string $network): void
    {
        if (! (bool) config('swap.enabled', true)) throw ValidationException::withMessages(['swap' => 'Swap temporairement désactivé.']);
        if (! array_key_exists($network, (array) config('swap.networks', []))) throw ValidationException::withMessages(['network' => 'Réseau non supporté.']);
    }

    private function randomCode(int $len): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $out = '';
        for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return $out;
    }
}
