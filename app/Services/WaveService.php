<?php

namespace App\Services;

use App\Models\WaveEvent;
use App\Models\WaveOrder;
use App\Models\MobileCryptoUser;
use App\Models\WavePortalEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaveService
{
    private const TX_REGEX = '/^(0x)?[a-f0-9]{64}$/i';
    private const PHONE_REGEX = '/^0[0-9]{9}$/';

    public function quoteBuy(float $fiat): array
    {
        $rate = (float) config('wave.rate_xof_per_usdt', 600);
        $fee = (float) config('wave.fee_percent', 0.5);
        $gross = $rate > 0 ? $fiat / $rate : 0;
        $net = round($gross * (1 - $fee / 100), 8);
        return ['fiat' => $fiat, 'usdt_gross' => round($gross, 8), 'usdt' => $net, 'rate' => $rate, 'fee_percent' => $fee];
    }

    public function quoteSell(float $usdt): array
    {
        $rate = (float) config('wave.rate_xof_per_usdt', 600);
        $fee = (float) config('wave.fee_percent', 0.5);
        $gross = $usdt * $rate;
        $fiat = round($gross * (1 - $fee / 100), 0);
        return ['usdt' => $usdt, 'fiat_gross' => round($gross, 2), 'fiat' => $fiat, 'rate' => $rate, 'fee_percent' => $fee];
    }

    public function createBuy(MobileCryptoUser $user, float $fiat, string $operator, string $channel = 'telegram', ?string $chatId = null): WaveOrder
    {
        $this->guardUser($user);
        $op = $this->operator($operator);
        $min = (float) config('wave.min_xof', 300);
        $max = (float) config('wave.max_xof', 60000);
        if ($fiat < $min - 1e-9) throw ValidationException::withMessages(['amount' => "Minimum {$min} XOF."]);
        if ($max > 0 && $fiat > $max + 1e-9) throw ValidationException::withMessages(['amount' => "Maximum {$max} XOF par commande."]);
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', (string) $user->payout_address)) {
            throw ValidationException::withMessages(['wallet' => 'Ajoute ton adresse USDT Polygon avec /setwallet 0x... avant un achat.']);
        }

        $quote = $this->quoteBuy($fiat);
        if ($quote['usdt_gross'] < (float) config('wave.min_usdt', 0.5)) throw ValidationException::withMessages(['amount' => 'Montant USDT trop faible.']);
        $paymentBase = trim((string) config('wave.operators.wave_ci.payment_link_base', ''));
        if ($paymentBase === '') throw ValidationException::withMessages(['wave' => 'Lien de paiement Wave marchand non configuré.']);
        $paymentUrl = $paymentBase . (str_contains($paymentBase, '?') ? '&' : '?') . 'amount=' . rawurlencode((string) (int) round($fiat));
        $this->checkDaily($user, $quote['usdt']);

        return DB::transaction(function () use ($user, $fiat, $quote, $op, $operator, $channel, $chatId, $paymentUrl) {
            $order = WaveOrder::create([
                'reference' => $this->reference(), 'mobile_user_id' => $user->id, 'channel' => $channel, 'chat_id' => $chatId,
                'direction' => WaveOrder::DIRECTION_BUY_USDT, 'operator' => $operator,
                'country' => $op['country'], 'currency' => $op['currency'],
                'fiat_amount' => $fiat, 'usdt_amount' => $quote['usdt'], 'usdt_gross' => $quote['usdt_gross'],
                'rate' => $quote['rate'], 'fee_percent' => $quote['fee_percent'],
                'status' => WaveOrder::STATUS_PENDING_PAYMENT, 'payment_url' => $paymentUrl,
                'payout_network' => (string) config('wave.payout_network', 'polygon'),
                'expires_at' => now()->addHours(2),
            ]);
            $this->event($order, 'order_created', 'user', (string) $user->id, ['direction' => 'buy_usdt', 'fiat' => $fiat]);
            return $order;
        });
    }

    public function createSell(MobileCryptoUser $user, float $usdt, string $phone, string $operator = 'wave_ci', string $channel = 'telegram', ?string $chatId = null): WaveOrder
    {
        $this->guardUser($user);
        $this->operator($operator);
        if (! preg_match(self::PHONE_REGEX, $phone)) throw ValidationException::withMessages(['phone' => 'Numéro mobile money invalide (10 chiffres, ex: 07XXXXXXXX).']);
        $min = (float) config('wave.min_usdt', 0.5);
        $max = (float) config('wave.max_usdt', 100);
        if ($usdt < $min - 1e-9) throw ValidationException::withMessages(['amount' => "Minimum {$min} USDT."]);
        if ($max > 0 && $usdt > $max + 1e-9) throw ValidationException::withMessages(['amount' => "Maximum {$max} USDT par commande."]);
        $quote = $this->quoteSell($usdt);
        if ($quote['fiat_gross'] < (float) config('wave.min_xof', 300)) throw ValidationException::withMessages(['amount' => 'Montant XOF trop faible.']);
        $this->checkDaily($user, $usdt);

        return DB::transaction(function () use ($user, $usdt, $phone, $operator, $quote, $channel, $chatId) {
            $order = WaveOrder::create([
                'reference' => $this->reference(), 'mobile_user_id' => $user->id, 'channel' => $channel, 'chat_id' => $chatId,
                'direction' => WaveOrder::DIRECTION_SELL_USDT, 'operator' => $operator,
                'country' => 'CI', 'currency' => 'XOF',
                'fiat_amount' => $quote['fiat'], 'usdt_amount' => $usdt, 'usdt_gross' => $usdt,
                'rate' => $quote['rate'], 'fee_percent' => $quote['fee_percent'],
                'status' => WaveOrder::STATUS_PENDING_DEPOSIT, 'payout_phone' => $phone,
                'payout_network' => (string) config('wave.payout_network', 'polygon'),
                'deposit_address' => (string) config('wave.usdt_deposit_address'),
                'expires_at' => now()->addHours(2),
            ]);
            $this->event($order, 'order_created', 'user', (string) $user->id, ['direction' => 'sell_usdt', 'usdt' => $usdt, 'phone' => $phone]);
            return $order;
        });
    }

    public function submitFiatProof(WaveOrder $order, ?string $ref): WaveOrder
    {
        $tx = trim((string) $ref);
        if ($tx === '' || ! preg_match('/^[A-Za-z0-9_-]{5,100}$/', $tx)) {
            throw ValidationException::withMessages(['ref' => 'ID de transaction Wave requis (ex: T_XXXXXXXX).']);
        }

        return DB::transaction(function () use ($order, $tx) {
            $locked = WaveOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->direction !== WaveOrder::DIRECTION_BUY_USDT) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’est pas un achat USDT.']);
            }
            if ($locked->payment_ref === $tx) return $locked;
            if ($locked->status !== WaveOrder::STATUS_PENDING_PAYMENT) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’attend plus de preuve de paiement.']);
            }

            $portal = WavePortalEvent::where('transaction_id', $tx)
                ->where('direction', 'in')
                ->lockForUpdate()
                ->first();
            if (! $portal) {
                throw ValidationException::withMessages(['ref' => 'ID de transaction pas encore reçu. Attends 15 s, recharge le portail, puis réessaie.']);
            }
            if ((float) $portal->amount !== (float) $locked->fiat_amount) {
                throw ValidationException::withMessages(['ref' => 'L’ID existe mais le montant ne correspond pas à la commande.']);
            }
            if ($portal->matched_order_id && (int) $portal->matched_order_id !== (int) $locked->id) {
                throw ValidationException::withMessages(['ref' => 'Cet ID est déjà rattaché à une autre commande.']);
            }

            $auto = (bool) config('wave.auto_payout_enabled', true)
                && (float) $locked->usdt_amount <= (float) config('wave.auto_payout_max_usdt', 10)
                && ! empty($locked->mobileUser?->payout_address);

            $locked->update([
                'status' => $auto ? WaveOrder::STATUS_APPROVED : WaveOrder::STATUS_PENDING_REVIEW,
                'payment_ref' => $tx,
                'verified_at' => now(),
                'admin_note' => $auto ? 'ID transaction Wave vérifié: payout USDT auto' : 'ID transaction Wave vérifié: revue admin',
            ]);
            $portal->update([
                'status' => $auto ? 'matched_auto_payout' : 'matched_manual_id',
                'matched_order_id' => $locked->id,
            ]);
            $this->event($locked, $auto ? 'fiat_id_matched_auto' : 'fiat_id_matched_manual', 'user', (string) $locked->mobile_user_id, ['transaction_id' => $tx, 'auto' => $auto]);
            return $locked;
        });
    }

    public function submitUsdtTx(WaveOrder $order, string $txHash): WaveOrder
    {
        $txHash = strtolower(trim($txHash));
        if (! preg_match(self::TX_REGEX, $txHash)) throw ValidationException::withMessages(['tx' => 'Hash de transaction USDT invalide.']);
        return DB::transaction(function () use ($order, $txHash) {
            $locked = WaveOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->direction !== WaveOrder::DIRECTION_SELL_USDT || $locked->status !== WaveOrder::STATUS_PENDING_DEPOSIT) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’attend pas de dépôt USDT.']);
            }
            if (WaveOrder::where('usdt_deposit_tx_hash', $txHash)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['tx' => 'Ce hash USDT est déjà utilisé.']);
            }
            $locked->update(['status' => WaveOrder::STATUS_PENDING_REVIEW, 'usdt_deposit_tx_hash' => $txHash, 'verified_at' => now()]);
            $this->event($locked, 'usdt_tx_submitted', 'user', (string) $locked->mobile_user_id, ['tx_hash' => $txHash]);
            return $locked;
        });
    }

    public function submitUsdtProof(WaveOrder $order): WaveOrder
    {
        return DB::transaction(function () use ($order) {
            $locked = WaveOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->direction !== WaveOrder::DIRECTION_SELL_USDT || $locked->status !== WaveOrder::STATUS_PENDING_DEPOSIT) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’attend pas de dépôt USDT.']);
            }
            $locked->update(['status' => WaveOrder::STATUS_PENDING_REVIEW, 'verified_at' => now()]);
            $this->event($locked, 'usdt_proof_submitted', 'user', (string) $locked->mobile_user_id, ['manual' => true]);
            return $locked;
        });
    }

    public function markPaid(WaveOrder $order, ?int $adminId = null, ?string $usdtPayout = null, ?string $fiatRef = null, ?string $note = null): WaveOrder
    {
        return DB::transaction(function () use ($order, $adminId, $usdtPayout, $fiatRef, $note) {
            $locked = WaveOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [WaveOrder::STATUS_PENDING_REVIEW, WaveOrder::STATUS_MANUAL_REVIEW], true)) {
                throw ValidationException::withMessages(['order' => 'La commande doit être en revue.']);
            }
            if ($locked->direction === WaveOrder::DIRECTION_BUY_USDT) {
                $usdtPayout = strtolower(trim((string) $usdtPayout));
                if (! preg_match(self::TX_REGEX, $usdtPayout)) throw ValidationException::withMessages(['usdt_payout_tx_hash' => 'Hash de paiement USDT requis.']);
                if (WaveOrder::where('usdt_payout_tx_hash', $usdtPayout)->whereKeyNot($locked->id)->exists()) throw ValidationException::withMessages(['usdt_payout_tx_hash' => 'Hash déjà utilisé.']);
                $locked->update(['status' => WaveOrder::STATUS_PAID, 'usdt_payout_tx_hash' => $usdtPayout, 'admin_id' => $adminId, 'admin_note' => $note, 'paid_at' => now()]);
            } else {
                $fiatRef = trim((string) $fiatRef);
                if ($fiatRef === '') throw ValidationException::withMessages(['fiat_tx_ref' => 'Référence du paiement mobile money requise.']);
                $locked->update(['status' => WaveOrder::STATUS_PAID, 'fiat_tx_ref' => $fiatRef, 'admin_id' => $adminId, 'admin_note' => $note, 'paid_at' => now()]);
            }
            $this->event($locked, 'order_paid', 'admin', $adminId ? (string) $adminId : null, ['usdt_tx' => $usdtPayout, 'fiat_ref' => $fiatRef]);
            return $locked;
        });
    }

    public function reject(WaveOrder $order, ?int $adminId = null, ?string $reason = null): WaveOrder
    {
        return DB::transaction(function () use ($order, $adminId, $reason) {
            $locked = WaveOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === WaveOrder::STATUS_PAID) throw ValidationException::withMessages(['order' => 'Commande déjà payée.']);
            $locked->update(['status' => WaveOrder::STATUS_REJECTED, 'admin_id' => $adminId, 'admin_note' => $reason, 'rejected_at' => now()]);
            $this->event($locked, 'order_rejected', 'admin', $adminId ? (string) $adminId : null, ['reason' => $reason]);
            return $locked;
        });
    }

    public function ordersForUser(MobileCryptoUser $user, int $limit = 20)
    {
        return WaveOrder::where('mobile_user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    public function ordersForAdmin(?string $status = null, int $limit = 100)
    {
        $q = WaveOrder::with('mobileUser')->latest('id');
        if ($status) $q->where('status', $status);
        return $q->limit($limit)->get();
    }

    public function latestOrderForChat(string $chatId, string $direction): ?WaveOrder
    {
        return WaveOrder::where('chat_id', $chatId)->where('direction', $direction)
            ->whereIn('status', [WaveOrder::STATUS_PENDING_PAYMENT, WaveOrder::STATUS_PENDING_DEPOSIT])
            ->latest('id')->first();
    }

    public function event(WaveOrder $order, string $type, ?string $actorType = null, ?string $actorId = null, array $payload = []): void
    {
        WaveEvent::create(['wave_order_id' => $order->id, 'type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'payload' => $payload ?: null]);
    }

    private function guardUser(MobileCryptoUser $user): void
    {
        if (! (bool) config('wave.enabled', true)) throw ValidationException::withMessages(['wave' => 'Service Wave temporairement désactivé.']);
        if (! $user->hasAcceptedTerms()) throw ValidationException::withMessages(['wave' => 'Accepte les conditions avec /accept.']);
    }

    private function operator(string $key): array
    {
        $op = (array) config("wave.operators.{$key}", []);
        if (! $op || empty($op['enabled'])) throw ValidationException::withMessages(['operator' => 'Opérateur mobile money non configuré.']);
        return $op;
    }

    private function checkDaily(MobileCryptoUser $user, float $usdt): void
    {
        $limit = (float) config('wave.daily_limit_usdt', 500);
        if ($limit <= 0) return;
        $total = (float) WaveOrder::where('mobile_user_id', $user->id)->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', [WaveOrder::STATUS_REJECTED, WaveOrder::STATUS_EXPIRED])->sum('usdt_amount');
        if ($total + $usdt > $limit + 1e-9) throw ValidationException::withMessages(['amount' => "Plafond journalier atteint ({$limit} USDT)."]);
    }

    private function reference(): string
    {
        do { $ref = 'WAVE-' . now()->format('Ymd') . '-' . $this->randomCode(6); } while (WaveOrder::where('reference', $ref)->exists());
        return $ref;
    }

    private function randomCode(int $len): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $out = '';
        for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return $out;
    }
}
