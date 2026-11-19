<?php

namespace App\Services;

use App\Models\OfframpDepositAddress;
use App\Models\OfframpEvent;
use App\Models\OfframpOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Off-ramp AVAXe (Pingala) → USDT (Polygon, BSC later).
 *
 * Each order gets a unique AVAXe deposit address from a dedicated HD range.
 * A Pingala watcher auto-approves exact small orders; a Polygon payout worker
 * sends the USDT; a Pingala sweeper moves the AVAXe to the hot wallet.
 */
class OfframpService
{
    public function quote(float $avaxe): array
    {
        $rate = (float) config('offramp.rate', 0);
        $fee = (float) config('offramp.fee_percent', 0);
        $usdt = round($avaxe * $rate * (1 - ($fee / 100)), 6);
        return ['avaxe' => $avaxe, 'usdt' => $usdt, 'rate' => $rate, 'fee_percent' => $fee];
    }

    public function createOrder(User $user, float $avaxe, string $channel = 'telegram', ?string $chatId = null): OfframpOrder
    {
        if (! (bool) config('offramp.enabled', true)) {
            throw ValidationException::withMessages(['offramp' => 'L’off-ramp est temporairement désactivé.']);
        }
        if (! $user->onramp_allowed) {
            throw ValidationException::withMessages(['offramp' => 'Ton compte n’est pas encore sur l’allowlist.']);
        }
        if ((bool) config('offramp.require_kyc', true) && $user->kyc_status !== 'approved') {
            throw ValidationException::withMessages(['offramp' => 'KYC requis avant l’off-ramp.']);
        }

        $wallet = trim((string) $user->wallet_address);
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $wallet)) {
            throw ValidationException::withMessages(['payout' => 'Adresse wallet invalide pour le paiement USDT.']);
        }

        $min = (float) config('offramp.min_avaxe', 0.1);
        $max = (float) config('offramp.max_avaxe', 10);
        $daily = (float) config('offramp.daily_limit_avaxe', 50);
        if ($avaxe < $min - 1e-9) {
            throw ValidationException::withMessages(['amount' => "Montant minimum : {$min} AVAXe."]);
        }
        if ($max > 0 && $avaxe > $max + 1e-9) {
            throw ValidationException::withMessages(['amount' => "Montant maximum par commande : {$max} AVAXe."]);
        }
        $dailyTotal = (float) OfframpOrder::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', [OfframpOrder::STATUS_REJECTED, OfframpOrder::STATUS_EXPIRED])
            ->sum('avaxe_amount');
        if ($daily > 0 && ($dailyTotal + $avaxe) > $daily + 1e-9) {
            throw ValidationException::withMessages(['amount' => "Plafond journalier atteint ({$daily} AVAXe)."]);
        }

        $quote = $this->quote($avaxe);
        if ($quote['usdt'] < (float) config('offramp.min_usdt', 0.1)) {
            throw ValidationException::withMessages(['amount' => 'Montant converti trop faible.']);
        }

        return DB::transaction(function () use ($user, $avaxe, $quote, $channel, $chatId, $wallet) {
            $address = OfframpDepositAddress::whereNull('order_id')
                ->orderBy('derivation_index')
                ->lockForUpdate()
                ->first();
            if (! $address) {
                throw ValidationException::withMessages(['offramp' => 'Aucune adresse AVAXe disponible.']);
            }

            do {
                $reference = 'OFF-' . now()->format('Ymd') . '-' . $this->randomCode(6);
            } while (OfframpOrder::where('reference', $reference)->exists());

            $order = OfframpOrder::create([
                'reference' => $reference,
                'user_id' => $user->id,
                'channel' => $channel,
                'chat_id' => $chatId,
                'payout_network' => (string) config('offramp.payout_network', 'polygon'),
                'payout_address' => $wallet,
                'avaxe_amount' => $avaxe,
                'usdt_amount' => $quote['usdt'],
                'rate' => $quote['rate'],
                'fee_percent' => $quote['fee_percent'],
                'status' => OfframpOrder::STATUS_PENDING_DEPOSIT,
                'deposit_address' => $address->address,
                'deposit_index' => $address->derivation_index,
                'expires_at' => now()->addHour(),
            ]);

            $address->update(['order_id' => $order->id, 'assigned_at' => now()]);
            $this->event($order, 'order_created', 'user', (string) $user->id, [
                'avaxe' => $avaxe, 'usdt' => $quote['usdt'], 'rate' => $quote['rate'],
                'payout_network' => $order->payout_network, 'deposit_address' => $address->address,
                'deposit_index' => $address->derivation_index,
            ]);

            return $order;
        });
    }

    public function approve(OfframpOrder $order, ?int $adminId = null, ?string $note = null): OfframpOrder
    {
        return DB::transaction(function () use ($order, $adminId, $note) {
            $locked = OfframpOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [OfframpOrder::STATUS_PENDING_REVIEW, OfframpOrder::STATUS_PAYOUT_DEFERRED], true)) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’est pas en attente de validation.']);
            }
            $locked->update([
                'status' => OfframpOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'admin_id' => $adminId,
                'admin_note' => $note,
                'payout_last_error' => null,
            ]);
            $this->event($locked, 'order_approved', 'admin', $adminId ? (string) $adminId : null, ['note' => $note]);
            return $locked;
        });
    }

    public function reject(OfframpOrder $order, ?int $adminId = null, ?string $reason = null): OfframpOrder
    {
        return DB::transaction(function () use ($order, $adminId, $reason) {
            $locked = OfframpOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [OfframpOrder::STATUS_PAID, OfframpOrder::STATUS_PAYING], true)) {
                throw ValidationException::withMessages(['order' => 'Impossible de refuser une commande en cours de paiement ou payée.']);
            }
            $locked->update([
                'status' => OfframpOrder::STATUS_REJECTED,
                'rejected_at' => now(),
                'admin_id' => $adminId,
                'admin_note' => $reason,
            ]);
            $this->event($locked, 'order_rejected', 'admin', $adminId ? (string) $adminId : null, ['reason' => $reason]);
            return $locked;
        });
    }

    public function ordersForUser(User $user, int $limit = 20)
    {
        return OfframpOrder::where('user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    public function ordersForAdmin(?string $status = null, int $limit = 100)
    {
        $query = OfframpOrder::with('user')->latest('id');
        if ($status) {
            $query->where('status', $status);
        }
        return $query->limit($limit)->get();
    }

    public function event(OfframpOrder $order, string $type, ?string $actorType = null, ?string $actorId = null, array $payload = []): void
    {
        OfframpEvent::create([
            'offramp_order_id' => $order->id,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload ?: null,
        ]);
    }

    public function depositAddressPoolSize(): int
    {
        return OfframpDepositAddress::whereNull('order_id')->count();
    }

    private function randomCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
