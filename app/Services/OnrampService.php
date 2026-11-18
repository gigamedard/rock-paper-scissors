<?php

namespace App\Services;

use App\Models\OnrampEvent;
use App\Models\OnrampLink;
use App\Models\OnrampLinkCode;
use App\Models\OnrampOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * MVP on-ramp USDT → AVAXe (manuel + allowlist).
 *
 * Aucun mouvement de fonds n'est automatisé ici :
 *  - l'utilisateur dépose l'USDT sur l'adresse trésorerie du réseau ;
 *  - l'admin vérifie le hash/confirmations ;
 *  - l'admin approuve ;
 *  - les AVAXe sont envoyés manuellement depuis la trésorerie dédiée ;
 *  - l'admin enregistre le hash de crédit.
 */
class OnrampService
{
    private const TX_HASH_REGEX = '/^(0x)?[a-f0-9]{64}$/i';

    public function issueLinkCode(User $user): array
    {
        $ttl = max(1, (int) config('onramp.link_code_ttl_minutes', 15));

        // Invalide les anciens codes non utilisés pour limiter les collisions.
        OnrampLinkCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        do {
            $code = $this->randomCode(8);
        } while (OnrampLinkCode::where('code', $code)->exists());

        $record = OnrampLinkCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $username = trim((string) config('onramp.telegram.bot_username'));
        $deepLink = $username !== '' ? "https://t.me/{$username}?start=link_{$code}" : null;

        return [
            'code' => $record->code,
            'expires_at' => $record->expires_at->toIso8601String(),
            'telegram_bot' => $username ?: null,
            'telegram_link' => $deepLink,
            'instructions' => $deepLink
                ? "Ouvre {$deepLink} ou envoie /link {$record->code} au bot Telegram."
                : "Envoie /link {$record->code} au bot Telegram.",
        ];
    }

    public function linkTelegram(string $code, string $chatId, ?string $username = null): OnrampLink
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code, $chatId, $username) {
            $record = OnrampLinkCode::where('code', $code)->lockForUpdate()->first();

            if (! $record || $record->used_at !== null) {
                throw ValidationException::withMessages(['link' => 'Code de liaison invalide ou déjà utilisé.']);
            }
            if ($record->expires_at->isPast()) {
                throw ValidationException::withMessages(['link' => 'Code de liaison expiré. Génère un nouveau code dans l’app.']);
            }

            $existingChat = OnrampLink::where('channel', 'telegram')
                ->where('chat_id', (string) $chatId)
                ->lockForUpdate()
                ->first();

            if ($existingChat && (int) $existingChat->user_id !== (int) $record->user_id) {
                throw ValidationException::withMessages(['link' => 'Ce chat Telegram est déjà lié à un autre compte.']);
            }

            $link = OnrampLink::updateOrCreate(
                ['user_id' => $record->user_id, 'channel' => 'telegram'],
                ['chat_id' => (string) $chatId, 'username' => $username]
            );

            $record->update(['used_at' => now()]);

            return $link;
        });
    }

    public function linkForTelegramChat(string $chatId): ?OnrampLink
    {
        return OnrampLink::with('user')
            ->where('channel', 'telegram')
            ->where('chat_id', (string) $chatId)
            ->first();
    }

    public function acceptTerms(OnrampLink $link): OnrampLink
    {
        $link->update([
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('onramp.terms_version', 'v1'),
        ]);

        return $link->refresh();
    }

    /** Packages avec le montant USDT calculé depuis le taux AVAX/USDT. */
    public function packages(): array
    {
        $rate = (float) config('onramp.avax_usdt_rate', 0);
        if ($rate <= 0) {
            return [];
        }
        $out = [];
        foreach ((array) config('onramp.packages', []) as $code => $package) {
            $avaxe = (float) ($package['avaxe'] ?? 0);
            if ($avaxe <= 0) {
                continue;
            }
            $out[$code] = [
                'code' => $code,
                'label' => (string) ($package['label'] ?? $code),
                'avaxe' => $avaxe,
                'usdt' => round($avaxe * $rate, 2),
            ];
        }
        return $out;
    }

    public function packageQuote(string $packageCode): ?array
    {
        return $this->packages()[$packageCode] ?? null;
    }

    public function createOrder(User $user, string $network, string $packageCode, string $channel = 'telegram', ?string $chatId = null): OnrampOrder
    {
        if (! (bool) config('onramp.enabled', false)) {
            throw ValidationException::withMessages(['onramp' => 'L’on-ramp est temporairement désactivé.']);
        }
        if (! $user->onramp_allowed) {
            throw ValidationException::withMessages(['onramp' => 'Ton compte n’est pas encore sur l’allowlist on-ramp.']);
        }
        if ((bool) config('onramp.require_kyc', true) && $user->kyc_status !== 'approved') {
            throw ValidationException::withMessages([
                'onramp' => 'KYC requis avant l’on-ramp. Complète la vérification sur ' . rtrim((string) config('app.url'), '/') . '/kyc.html',
            ]);
        }

        $networkConfig = config("onramp.networks.{$network}");
        if (! is_array($networkConfig)) {
            throw ValidationException::withMessages(['network' => 'Réseau USDT non supporté.']);
        }
        if (trim((string) ($networkConfig['address'] ?? '')) === '') {
            throw ValidationException::withMessages(['network' => 'Adresse de dépôt non configurée pour ce réseau.']);
        }

        $package = $this->packageQuote($packageCode);
        if (! is_array($package)) {
            throw ValidationException::withMessages(['package' => 'Package inconnu ou taux AVAX/USDT non configuré.']);
        }

        $usdt = (float) ($package['usdt'] ?? 0);
        $avaxe = (float) ($package['avaxe'] ?? 0);
        $maxOrder = (float) config('onramp.max_order_usdt', 50);
        $dailyLimit = (float) config('onramp.daily_limit_usdt', 100);

        if ($usdt <= 0 || $avaxe <= 0) {
            throw ValidationException::withMessages(['package' => 'Package invalide.']);
        }
        if ($maxOrder > 0 && $usdt > $maxOrder + 1e-9) {
            throw ValidationException::withMessages(['package' => "Montant maximum par commande : {$maxOrder} USDT."]);
        }

        $dailyTotal = (float) OnrampOrder::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->where('status', '!=', OnrampOrder::STATUS_REJECTED)
            ->sum('usdt_amount');

        if ($dailyLimit > 0 && ($dailyTotal + $usdt) > $dailyLimit + 1e-9) {
            throw ValidationException::withMessages(['package' => "Plafond journalier atteint ({$dailyLimit} USDT)."]);
        }

        return DB::transaction(function () use ($user, $network, $packageCode, $usdt, $avaxe, $networkConfig, $channel, $chatId) {
            do {
                $reference = 'BP-' . now()->format('Ymd') . '-' . $this->randomCode(6);
            } while (OnrampOrder::where('reference', $reference)->exists());

            $order = OnrampOrder::create([
                'reference' => $reference,
                'user_id' => $user->id,
                'channel' => $channel,
                'chat_id' => $chatId,
                'network' => $network,
                'package_code' => $packageCode,
                'usdt_amount' => $usdt,
                'avaxe_amount' => $avaxe,
                'deposit_address' => (string) $networkConfig['address'],
                'status' => OnrampOrder::STATUS_PENDING_DEPOSIT,
            ]);

            $this->event($order, 'order_created', 'user', (string) $user->id, [
                'network' => $network,
                'package' => $packageCode,
                'usdt' => $usdt,
                'avaxe' => $avaxe,
            ]);

            return $order;
        });
    }

    public function submitTx(OnrampOrder $order, string $txHash): OnrampOrder
    {
        $txHash = strtolower(trim($txHash));
        if (! preg_match(self::TX_HASH_REGEX, $txHash)) {
            throw ValidationException::withMessages(['tx_hash' => 'Hash de transaction invalide.']);
        }

        return DB::transaction(function () use ($order, $txHash) {
            $locked = OnrampOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OnrampOrder::STATUS_PENDING_DEPOSIT) {
                throw ValidationException::withMessages(['order' => 'Cette commande n’attend pas de dépôt.']);
            }
            if (OnrampOrder::where('tx_hash', $txHash)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['tx_hash' => 'Ce hash de transaction est déjà utilisé.']);
            }

            $locked->update([
                'tx_hash' => $txHash,
                'status' => OnrampOrder::STATUS_PENDING_REVIEW,
                'submitted_at' => now(),
            ]);
            $this->event($locked, 'tx_submitted', 'user', (string) $locked->user_id, ['tx_hash' => $txHash]);

            return $locked;
        });
    }

    public function approve(OnrampOrder $order, ?int $adminId = null, ?string $note = null): OnrampOrder
    {
        return DB::transaction(function () use ($order, $adminId, $note) {
            $locked = OnrampOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OnrampOrder::STATUS_PENDING_REVIEW) {
                throw ValidationException::withMessages(['order' => 'La commande n’est pas en attente de revue.']);
            }

            $locked->update([
                'status' => OnrampOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'admin_id' => $adminId,
                'admin_note' => $note,
            ]);
            $this->event($locked, 'order_approved', 'admin', $adminId ? (string) $adminId : null, ['note' => $note]);

            return $locked;
        });
    }

    public function credit(OnrampOrder $order, string $creditTxHash, ?int $adminId = null): OnrampOrder
    {
        $creditTxHash = strtolower(trim($creditTxHash));
        if (! preg_match(self::TX_HASH_REGEX, $creditTxHash)) {
            throw ValidationException::withMessages(['credit_tx_hash' => 'Hash de crédit AVAXe invalide.']);
        }

        return DB::transaction(function () use ($order, $creditTxHash, $adminId) {
            $locked = OnrampOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [OnrampOrder::STATUS_APPROVED, OnrampOrder::STATUS_MANUAL_REVIEW], true)) {
                throw ValidationException::withMessages(['order' => 'La commande doit être approuvée (ou en revue manuelle) avant le crédit.']);
            }
            if (OnrampOrder::where('credit_tx_hash', $creditTxHash)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['credit_tx_hash' => 'Ce hash de crédit est déjà utilisé.']);
            }

            $locked->update([
                'status' => OnrampOrder::STATUS_CREDITED,
                'credit_tx_hash' => $creditTxHash,
                'credited_at' => now(),
                'admin_id' => $adminId ?: $locked->admin_id,
            ]);
            $this->event($locked, 'order_credited', 'admin', $adminId ? (string) $adminId : null, ['credit_tx_hash' => $creditTxHash]);

            return $locked;
        });
    }

    public function reject(OnrampOrder $order, ?int $adminId = null, ?string $reason = null): OnrampOrder
    {
        return DB::transaction(function () use ($order, $adminId, $reason) {
            $locked = OnrampOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OnrampOrder::STATUS_CREDITED) {
                throw ValidationException::withMessages(['order' => 'Impossible de refuser une commande déjà créditée.']);
            }

            $locked->update([
                'status' => OnrampOrder::STATUS_REJECTED,
                'rejected_at' => now(),
                'admin_id' => $adminId,
                'admin_note' => $reason,
            ]);
            $this->event($locked, 'order_rejected', 'admin', $adminId ? (string) $adminId : null, ['reason' => $reason]);

            return $locked;
        });
    }

    public function latestPendingOrderForChat(string $chatId): ?OnrampOrder
    {
        return OnrampOrder::where('chat_id', (string) $chatId)
            ->where('status', OnrampOrder::STATUS_PENDING_DEPOSIT)
            ->latest('id')
            ->first();
    }

    public function ordersForUser(User $user, int $limit = 10)
    {
        return OnrampOrder::where('user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    public function ordersForAdmin(?string $status = null, int $limit = 50)
    {
        $query = OnrampOrder::with('user')->latest('id');
        if ($status) {
            $query->where('status', $status);
        }
        return $query->limit($limit)->get();
    }

    public function allowUser(User $user, bool $allowed): User
    {
        $user->update(['onramp_allowed' => $allowed]);
        return $user->refresh();
    }

    public function event(OnrampOrder $order, string $type, ?string $actorType = null, ?string $actorId = null, array $payload = []): void
    {
        OnrampEvent::create([
            'onramp_order_id' => $order->id,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload ?: null,
        ]);
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
