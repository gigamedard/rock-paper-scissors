<?php

namespace App\Services;

use App\Models\MobileCryptoUser;
use App\Models\WaveOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Bot MobileCrypto indépendant, sans KYC, boutons + petits montants test. */
class WaveTelegramBot
{
    public function __construct(
        private MobileCryptoService $mobile,
        private WaveService $wave,
        private WaveTelegramService $telegram
    ) {
    }

    public function handle(array $update): void
    {
        try {
            if (isset($update['message'])) { $this->handleMessage($update['message']); return; }
            if (isset($update['callback_query'])) { $this->handleCallback($update['callback_query']); }
        } catch (Throwable $e) {
            Log::error('[WaveBot] update failed: ' . $e->getMessage());
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = isset($message['chat']['id']) ? (string) $message['chat']['id'] : null;
        if (! $chatId) return;
        $username = $message['from']['username'] ?? null;
        $user = $this->mobile->touch($chatId, $username);

        // Contact partagé pour un ordre de vente en attente.
        if (isset($message['contact']['phone_number'])) {
            $this->handleSellContact($chatId, $user, (string) $message['contact']['phone_number']);
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') return;
        $text = $this->alias($text);
        if (($pendingWallet = Cache::get($this->walletCacheKey($chatId))) !== null && ! str_starts_with($text, '/')) {
            Cache::forget($this->walletCacheKey($chatId));
            $this->setWallet($chatId, $user, $text);
            return;
        }
        if (($proofOrderId = Cache::get($this->proofCacheKey($chatId))) !== null && ! str_starts_with($text, '/')) {
            Cache::forget($this->proofCacheKey($chatId));
            $order = WaveOrder::where('id', (int) $proofOrderId)->where('chat_id', $chatId)->first();
            if ($order) { $this->submitWaveFiatProof($chatId, $user, [$text], $order); return; }
        }
        [$command, $args] = $this->parseCommand($text);

        if ($command === '/start') { $this->sendStart($chatId, $user); return; }
        if ($command === '/register') { $this->telegram->sendMessage($chatId, 'Pas d’inscription ni de KYC pour cette phase de test. Accepte les conditions avec <code>/accept</code>.'); return; }
        if ($command === '/setwallet') { $this->setWallet($chatId, $user, $args[0] ?? ''); return; }
        if ($command === '/wallet') { $this->sendWallet($chatId, $user); return; }
        if ($command === '/accept') { $this->mobile->acceptTerms($user); $this->telegram->sendMessage($chatId, '✅ Conditions acceptées.'); $this->sendMenu($chatId); return; }
        if ($command === '/profile' || $command === '/status') { $this->sendProfile($chatId, $user); return; }
        if ($command === '/wave') { $this->sendWaveHelp($chatId, $user); return; }
        if ($command === '/wavebuy') { if (! isset($args[0])) { $this->sendBuyAmounts($chatId); } else { $this->createWaveBuy($chatId, $user, $args[0]); } return; }
        if ($command === '/wavesell') { if (! isset($args[0])) { $this->sendSellAmounts($chatId); } else { $this->createWaveSell($chatId, $user, $args[0], $args[1] ?? null); } return; }
        if ($command === '/wavepaid') { $this->submitWaveFiatProof($chatId, $user, $args); return; }
        if ($command === '/wavetx') { $this->submitWaveUsdtTx($chatId, $user, $args); return; }
        if ($command === '/waveorders') { $this->sendWaveOrders($chatId, $user); return; }
        if ($command === '/whoami') { $this->telegram->sendMessage($chatId, "🆔 Chat ID : <code>{$this->esc($chatId)}</code>\nCompte MobileCrypto #{$user->id}"); return; }
        if ($command === '/link') { $this->telegram->sendMessage($chatId, 'Aucun lien BattlePool nécessaire : MobileCrypto est indépendant.'); return; }
        if ($command === '/help') { $this->sendHelp($chatId); return; }

        // Si un montant de vente est en attente, accepter un numéro tapé.
        if (($pending = Cache::get($this->sellCacheKey($chatId))) !== null && preg_match('/^(?:\+?225)?0?[0-9]{9,10}$/', preg_replace('/\s+/', '', $text))) {
            $this->handleSellContact($chatId, $user, $text);
            return;
        }

        $this->telegram->sendMessage($chatId, 'Utilise les boutons du menu ou <code>/help</code>.');
    }

    private function handleCallback(array $callback): void
    {
        $id = (string) ($callback['id'] ?? '');
        $chatId = isset($callback['message']['chat']['id']) ? (string) $callback['message']['chat']['id'] : null;
        $data = (string) ($callback['data'] ?? '');
        if (! $chatId) return;
        $user = $this->mobile->touch($chatId, $callback['message']['chat']['username'] ?? null);

        if ($data === 'terms_accept') {
            $this->mobile->acceptTerms($user);
            $this->telegram->answerCallbackQuery($id, 'Conditions acceptées');
            $this->telegram->sendMessage($chatId, '✅ Conditions acceptées.');
            $this->sendMenu($chatId);
            return;
        }
        if (str_starts_with($data, 'wb:buy:')) {
            $this->telegram->answerCallbackQuery($id);
            $this->createWaveBuy($chatId, $user, substr($data, 7));
            return;
        }
        if (str_starts_with($data, 'wb:sell:')) {
            $this->telegram->answerCallbackQuery($id);
            $this->askSellPhone($chatId, substr($data, 8));
            return;
        }
        if ($data === 'wb:wallet') { $this->sendWallet($chatId, $user); $this->telegram->answerCallbackQuery($id); return; }
        if ($data === 'wb:wallet:set') {
            Cache::put($this->walletCacheKey($chatId), true, now()->addMinutes(10));
            $this->telegram->answerCallbackQuery($id, 'Envoie ton adresse Polygon');
            $this->telegram->sendMessage($chatId, "Envoie ton adresse USDT Polygon (0x...).");
            return;
        }
        if (str_starts_with($data, 'wb:paid:')) {
            $order = WaveOrder::where('id', (int) substr($data, 8))->where('chat_id', $chatId)->first();
            if (! $order) { $this->telegram->answerCallbackQuery($id, 'Commande introuvable'); return; }
            Cache::put($this->proofCacheKey($chatId), $order->id, now()->addMinutes(15));
            $this->telegram->answerCallbackQuery($id, 'Envoie l’ID de transaction');
            $this->telegram->sendMessage($chatId, "Envoie maintenant l’ID de transaction Wave de <b>{$order->reference}</b> (ex: <code>T_XXXXXXXX</code>).", ['force_reply' => true, 'input_field_placeholder' => 'T_...']);
            return;
        }
        if (str_starts_with($data, 'wb:sent:')) {
            $order = WaveOrder::where('id', (int) substr($data, 8))->where('chat_id', $chatId)->first();
            if (! $order) { $this->telegram->answerCallbackQuery($id, 'Commande introuvable'); return; }
            try {
                $this->wave->submitUsdtProof($order);
                $this->telegram->answerCallbackQuery($id, 'Dépôt soumis');
                $this->telegram->sendMessage($chatId, "✅ Dépôt USDT soumis pour <b>{$order->reference}</b>. Vérification admin.");
                $this->telegram->sendAdmin("🔎 Wave VENTE à vérifier (bouton)\n{$order->reference}\n{$order->usdt_amount} USDT → {$order->fiat_amount} XOF");
            } catch (ValidationException $e) { $this->telegram->answerCallbackQuery($id, $this->firstError($e)); }
            return;
        }
    }

    private function sendStart(string $chatId, MobileCryptoUser $user): void
    {
        if (! $user->hasAcceptedTerms()) {
            $keyboard = ['inline_keyboard' => [[['text' => '✅ J’accepte les conditions', 'callback_data' => 'terms_accept']]]];
            $this->telegram->sendMessage($chatId,
                "<b>MobileCrypto — Wave ↔ USDT</b>\n\n" .
                "Phase de test, petits montants, sans KYC.\n" .
                "Aucune donnée personnelle n’est demandée ici.\n\n" .
                "Accepte les conditions pour continuer.",
                $keyboard
            );
            return;
        }
        $this->sendMenu($chatId);
    }

    private function sendMenu(string $chatId): void
    {
        $keyboard = ['keyboard' => [
            [['text' => '💳 Acheter USDT'], ['text' => '💵 Vendre USDT']],
            [['text' => '👛 Wallet'], ['text' => '📄 Commandes'], ['text' => '❓ Aide']],
        ], 'resize_keyboard' => true];
        $this->telegram->sendMessage($chatId, "Menu MobileCrypto — choisis une action.", $keyboard);
    }

    private function sendHelp(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>MobileCrypto — test</b>\n" .
            "Sans KYC, petits montants uniquement.\n\n" .
            "Utilise les boutons : Acheter, Vendre, Wallet, Commandes.\n" .
            "Achat : lien Wave marchand, montant exact.\n" .
            "Vente : dépôt USDT Polygon, paiement XOF manuel.\n" .
            "Taux 1 USDT = 600 XOF · frais 0,5 % · max 10 USDT / 6 000 XOF · 20 USDT/jour.\n\n" .
            "/setwallet 0x... pour le wallet USDT d’achat."
        );
        $this->sendMenu($chatId);
    }

    private function sendWallet(string $chatId, MobileCryptoUser $user): void
    {
        $keyboard = ['inline_keyboard' => [[['text' => '✏️ ' . ($user->payout_address ? 'Modifier le wallet' : 'Ajouter un wallet'), 'callback_data' => 'wb:wallet:set']]]];
        if ($user->payout_address) {
            $this->telegram->sendMessage($chatId, "👛 Wallet USDT Polygon :\n<code>{$this->esc($user->payout_address)}</code>", $keyboard);
        } else {
            $this->telegram->sendMessage($chatId, "👛 Aucun wallet USDT enregistré.", $keyboard);
        }
    }

    private function setWallet(string $chatId, MobileCryptoUser $user, string $address): void
    {
        try {
            $user = $this->mobile->setPayoutAddress($user, $address);
            $this->telegram->sendMessage($chatId, "✅ Wallet USDT Polygon enregistré :\n<code>{$this->esc($user->payout_address)}</code>");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function sendProfile(string $chatId, MobileCryptoUser $user): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Compte MobileCrypto #{$user->id}</b>\n" .
            "Wallet USDT : " . ($user->payout_address ? "<code>{$this->esc($user->payout_address)}</code>" : 'non renseigné') . "\n" .
            "Conditions : " . ($user->hasAcceptedTerms() ? 'acceptées' : 'non acceptées') . "\n" .
            "Mode test sans KYC."
        );
    }

    private function sendWaveHelp(string $chatId, MobileCryptoUser $user): void
    {
        $this->sendHelp($chatId);
    }

    private function sendBuyAmounts(string $chatId): void
    {
        $rows = [];
        foreach ([300, 600, 1200, 3000, 6000] as $amount) {
            $rows[] = ['text' => "{$amount} XOF", 'callback_data' => 'wb:buy:' . $amount];
        }
        $keyboard = ['inline_keyboard' => array_chunk($rows, 3)];
        $this->telegram->sendMessage($chatId, "💳 Combien de XOF veux-tu payer via Wave ?", $keyboard);
    }

    private function sendSellAmounts(string $chatId): void
    {
        $rows = [];
        foreach ([0.5, 1, 2, 5, 10] as $amount) {
            $rows[] = ['text' => "{$amount} USDT", 'callback_data' => 'wb:sell:' . $amount];
        }
        $keyboard = ['inline_keyboard' => array_chunk($rows, 3)];
        $this->telegram->sendMessage($chatId, "💵 Combien d’USDT veux-tu vendre ?", $keyboard);
    }

    private function createWaveBuy(string $chatId, MobileCryptoUser $user, string $rawFiat): void
    {
        if (! $user->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte les conditions avec <code>/accept</code>.'); return; }
        if (! $user->payout_address) {
            $keyboard = ['inline_keyboard' => [[['text' => '👛 Ajouter mon wallet', 'callback_data' => 'wb:wallet:set']]]];
            $this->telegram->sendMessage($chatId, 'Ajoute d’abord ton wallet USDT Polygon.', $keyboard);
            return;
        }
        $fiat = (float) str_replace([' ', ','], ['', '.'], trim($rawFiat));
        if ($fiat <= 0) { $this->telegram->sendMessage($chatId, 'Montant invalide.'); return; }
        try {
            $o = $this->wave->createBuy($user, $fiat, 'wave_ci', 'telegram', $chatId);
            $link = (string) $o->payment_url;
            $keyboard = ['inline_keyboard' => [
                [['text' => '💳 Payer avec Wave', 'url' => $link]],
                [['text' => '✅ J’ai payé', 'callback_data' => 'wb:paid:' . $o->id]],
                [['text' => '📋 Copier le lien', 'copy_text' => ['text' => $link]]],
            ]];
            $text = "🌊 <b>Achat USDT via Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Montant exact : <b>{$this->fmt((float) $o->fiat_amount)} XOF</b>\n" .
                "Clique sur <b>Payer avec Wave</b>.\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur <code>{$this->esc($user->payout_address)}</code>.\n" .
                "Après paiement, clique sur <b>✅ J’ai payé</b> puis envoie l’ID de transaction Wave.\n\n⚠️ Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text . "\n\n" . $link);
            $this->telegram->sendAdmin("🌊 Wave ACHAT\n{$o->reference}\nMobileCrypto #{$user->id}\n{$o->fiat_amount} XOF → {$o->usdt_amount} USDT");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function askSellPhone(string $chatId, string $amount): void
    {
        $amount = (float) str_replace(',', '.', $amount);
        if ($amount <= 0) { $this->sendSellAmounts($chatId); return; }
        Cache::put($this->sellCacheKey($chatId), $amount, now()->addMinutes(10));
        $keyboard = ['keyboard' => [[['text' => '📱 Partager mon numéro', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true];
        $this->telegram->sendMessage($chatId, "Pour recevoir les XOF, partage ton numéro Wave avec le bouton ci-dessous, ou tape-le.", $keyboard);
    }

    private function handleSellContact(string $chatId, MobileCryptoUser $user, string $rawPhone): void
    {
        $amount = Cache::pull($this->sellCacheKey($chatId));
        if ($amount === null) { $this->telegram->sendMessage($chatId, 'Aucune vente en attente.'); return; }
        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (str_starts_with($digits, '225') && strlen($digits) === 13) $digits = substr($digits, -10);
        if (! preg_match('/^0[0-9]{9}$/', $digits)) { $this->telegram->sendMessage($chatId, 'Numéro invalide. Exemple : 07XXXXXXXX.'); return; }
        $this->createWaveSell($chatId, $user, (string) $amount, $digits);
    }

    private function createWaveSell(string $chatId, MobileCryptoUser $user, string $rawUsdt, ?string $phone = null): void
    {
        if (! $user->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte les conditions avec <code>/accept</code>.'); return; }
        $usdt = (float) str_replace(',', '.', trim($rawUsdt));
        if ($usdt <= 0) { $this->sendSellAmounts($chatId); return; }
        $phone = $phone ? preg_replace('/\D+/', '', $phone) : null;
        if ($phone && strlen($phone) === 13 && str_starts_with($phone, '225')) $phone = substr($phone, -10);
        if (! $phone || ! preg_match('/^0[0-9]{9}$/', $phone)) { $this->askSellPhone($chatId, (string) $usdt); return; }
        try {
            $o = $this->wave->createSell($user, $usdt, $phone, 'wave_ci', 'telegram', $chatId);
            $addr = (string) $o->deposit_address;
            $keyboard = ['inline_keyboard' => [
                [['text' => '📋 Copier l’adresse USDT', 'copy_text' => ['text' => $addr]]],
                [['text' => '✅ J’ai envoyé les USDT', 'callback_data' => 'wb:sent:' . $o->id]],
            ]];
            $text = "🌊 <b>Vente USDT → Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur <b>Polygon</b> à :\n<pre>" . $this->esc($addr) . "</pre>\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->fiat_amount)} XOF</b> sur <code>{$this->esc($phone)}</code>.\n" .
                "Après envoi, clique sur <b>✅ J’ai envoyé les USDT</b>.\n\n⚠️ Polygon uniquement. Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Wave VENTE\n{$o->reference}\nMobileCrypto #{$user->id}\n{$o->usdt_amount} USDT → {$o->fiat_amount} XOF\nTel: {$phone}");
            $this->sendMenu($chatId);
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveFiatProof(string $chatId, MobileCryptoUser $user, array $args, ?WaveOrder $forcedOrder = null): void
    {
        $o = $forcedOrder;
        if (! $o) {
            $pending = WaveOrder::where('chat_id', $chatId)
                ->where('direction', WaveOrder::DIRECTION_BUY_USDT)
                ->where('status', WaveOrder::STATUS_PENDING_PAYMENT)
                ->latest('id')
                ->get();
            if ($pending->count() !== 1) {
                $this->telegram->sendMessage($chatId, 'Utilise le bouton <b>✅ J’ai payé</b> sur la commande concernée, puis envoie son ID.');
                return;
            }
            $o = $pending->first();
        }
        $ref = trim(implode(' ', $args));
        try {
            $o = $this->wave->submitFiatProof($o, $ref);
            $msg = $o->status === WaveOrder::STATUS_APPROVED
                ? "✅ ID de transaction reconnu pour <b>{$o->reference}</b>. Envoi USDT automatique en cours."
                : "✅ ID de transaction reconnu pour <b>{$o->reference}</b>. Vérification admin.";
            $this->telegram->sendMessage($chatId, $msg);
            $this->telegram->sendAdmin("🔎 Wave ACHAT ID reconnu\n{$o->reference}\nMobileCrypto #{$user->id}\n{$o->fiat_amount} XOF\nID: {$ref}\nStatut: {$o->status}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveUsdtTx(string $chatId, MobileCryptoUser $user, array $args): void
    {
        $hash = trim((string) ($args[1] ?? $args[0] ?? ''));
        $o = $this->wave->latestOrderForChat($chatId, WaveOrder::DIRECTION_SELL_USDT);
        if (! $o) { $this->telegram->sendMessage($chatId, 'Aucune commande VENTE en attente.'); return; }
        try {
            $o = $this->wave->submitUsdtTx($o, $hash);
            $this->telegram->sendMessage($chatId, "✅ Dépôt USDT soumis pour <b>{$o->reference}</b>. Vérification admin.");
            $this->telegram->sendAdmin("🔎 Wave VENTE à vérifier\n{$o->reference}\nMobileCrypto #{$user->id}\n{$o->usdt_amount} USDT\nTx: {$o->usdt_deposit_tx_hash}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function sendWaveOrders(string $chatId, MobileCryptoUser $user): void
    {
        $orders = $this->wave->ordersForUser($user, 10);
        if ($orders->isEmpty()) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave.'); return; }
        $lines = ['<b>Tes commandes Wave</b>'];
        foreach ($orders as $o) {
            $lines[] = "• <code>{$o->reference}</code> — " . ($o->direction === 'buy_usdt' ? "{$o->fiat_amount} XOF → {$o->usdt_amount} USDT" : "{$o->usdt_amount} USDT → {$o->fiat_amount} XOF") . " — <b>{$o->status}</b>";
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function alias(string $text): string
    {
        return [
            '💳 Acheter USDT' => '/wavebuy',
            '💵 Vendre USDT' => '/wavesell',
            '👛 Wallet' => '/wallet',
            '📄 Commandes' => '/waveorders',
            '❓ Aide' => '/help',
        ][$text] ?? $text;
    }

    private function sellCacheKey(string $chatId): string { return 'wave_sell_amount_' . $chatId; }
    private function walletCacheKey(string $chatId): string { return 'wave_wallet_set_' . $chatId; }
    private function proofCacheKey(string $chatId): string { return 'wave_fiat_proof_' . $chatId; }

    private function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $command = strtolower((string) array_shift($parts));
        $command = preg_replace('/@\w+$/', '', $command) ?? $command;
        return [$command, $parts];
    }

    private function firstError(ValidationException $e): string
    {
        $errors = $e->errors(); $first = reset($errors);
        return is_array($first) ? (string) reset($first) : (string) $first;
    }

    private function fmt(float $v): string { return number_format($v, 6, '.', ''); }
    private function esc(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
