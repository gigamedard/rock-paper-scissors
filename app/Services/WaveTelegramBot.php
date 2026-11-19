<?php

namespace App\Services;

use App\Models\MobileCryptoUser;
use App\Models\WaveOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Bot MobileCrypto indépendant (Wave XOF ↔ USDT, MVP manuel). */
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
        $text = trim((string) ($message['text'] ?? ''));
        $username = $message['from']['username'] ?? null;
        if (! $chatId || $text === '') return;

        $user = $this->mobile->touch($chatId, $username);
        [$command, $args] = $this->parseCommand($text);

        if ($command === '/start') { $this->sendStart($chatId, $user); return; }
        if ($command === '/register') { $this->register($chatId, $user, $args); return; }
        if ($command === '/setwallet') { $this->setWallet($chatId, $user, $args[0] ?? ''); return; }
        if ($command === '/accept') { $this->acceptTerms($chatId, $user); return; }
        if ($command === '/profile' || $command === '/status') { $this->sendProfile($chatId, $user); return; }
        if ($command === '/wave') { $this->sendWaveHelp($chatId, $user); return; }
        if ($command === '/wavebuy') { $this->createWaveBuy($chatId, $user, $args[0] ?? ''); return; }
        if ($command === '/wavesell') { $this->createWaveSell($chatId, $user, $args); return; }
        if ($command === '/wavepaid') { $this->submitWaveFiatProof($chatId, $user, $args); return; }
        if ($command === '/wavetx') { $this->submitWaveUsdtTx($chatId, $user, $args); return; }
        if ($command === '/waveorders') { $this->sendWaveOrders($chatId, $user); return; }
        if ($command === '/whoami') { $this->telegram->sendMessage($chatId, "🆔 Chat ID : <code>{$this->esc($chatId)}</code>\nCompte MobileCrypto #{$user->id}"); return; }
        if ($command === '/link') { $this->telegram->sendMessage($chatId, 'Plus besoin de /link : ce bot est indépendant. Utilise <code>/start</code>.'); return; }
        if ($command === '/help') { $this->sendHelp($chatId); return; }
        $this->telegram->sendMessage($chatId, 'Commande inconnue. Utilise <code>/help</code>.');
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
            $this->telegram->sendMessage($chatId, '✅ Conditions acceptées. Utilise <code>/wave</code>.');
        }
    }

    private function sendStart(string $chatId, MobileCryptoUser $user): void
    {
        if (! $user->full_name) { $this->sendRegistrationInstructions($chatId); return; }
        if ($user->kyc_status !== MobileCryptoUser::KYC_APPROVED) {
            $this->sendProfile($chatId, $user);
            $this->telegram->sendMessage($chatId, '⏳ KYC MobileCrypto en attente de validation admin.');
            return;
        }
        if (! $user->hasAcceptedTerms()) { $this->sendTerms($chatId); return; }
        $this->sendWaveHelp($chatId, $user);
    }

    private function sendRegistrationInstructions(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Bienvenue sur MobileCrypto</b>\n\n" .
            "Service indépendant Wave Côte d’Ivoire ↔ USDT.\n\n" .
            "1. Enregistre-toi :\n<code>/register NOM COMPLET | 0758431653 | CI</code>\n\n" .
            "2. Ajoute ton adresse USDT Polygon (pour les achats) :\n<code>/setwallet 0x...</code>\n\n" .
            "3. Accepte les conditions : <code>/accept</code>\n" .
            "4. Attends la validation KYC admin, puis <code>/wave</code>."
        );
    }

    private function register(string $chatId, MobileCryptoUser $user, array $args): void
    {
        $raw = implode(' ', $args);
        $parts = array_map('trim', preg_split('/\s*\|\s*/', $raw) ?: []);
        if (count($parts) < 2) {
            $this->telegram->sendMessage($chatId, 'Usage : <code>/register NOM COMPLET | 0758431653 | CI</code>');
            return;
        }
        try {
            $user = $this->mobile->register($user, $parts[0], $parts[1], $parts[2] ?? 'CI');
            $this->telegram->sendMessage($chatId, "✅ Compte créé : <b>#{$user->id}</b>.\nKYC en attente. Ajoute ton wallet USDT avec <code>/setwallet 0x...</code>, puis attends l’admin.");
            $this->telegram->sendAdmin("🆕 KYC MobileCrypto à valider\nUser #{$user->id} · {$user->full_name}\nTel: {$user->phone}\nPays: {$user->country}\nWallet: " . ($user->payout_address ?: 'non renseigné'));
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function setWallet(string $chatId, MobileCryptoUser $user, string $address): void
    {
        try {
            $user = $this->mobile->setPayoutAddress($user, $address);
            $this->telegram->sendMessage($chatId, "✅ Wallet USDT Polygon enregistré :\n<code>{$this->esc($user->payout_address)}</code>");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function acceptTerms(string $chatId, MobileCryptoUser $user): void
    {
        $this->mobile->acceptTerms($user);
        $this->telegram->sendMessage($chatId, '✅ Conditions acceptées.');
    }

    private function sendTerms(string $chatId): void
    {
        $terms = (string) config('onramp.terms_url', '');
        $keyboard = ['inline_keyboard' => [[['text' => '✅ J’accepte les conditions', 'callback_data' => 'terms_accept']]]];
        $this->telegram->sendMessage($chatId,
            "Avant d’utiliser MobileCrypto, accepte les conditions.\n" . ($terms ? $terms : ''),
            $keyboard
        );
    }

    private function sendProfile(string $chatId, MobileCryptoUser $user): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Compte MobileCrypto #{$user->id}</b>\n" .
            "Nom : " . $this->esc($user->full_name ?: '—') . "\n" .
            "Téléphone : " . $this->esc($user->phone ?: '—') . "\n" .
            "Pays : " . $this->esc($user->country) . "\n" .
            "KYC : <b>{$user->kyc_status}</b> · allowlist : " . ($user->allowlist ? 'oui' : 'non') . "\n" .
            "Wallet USDT : " . ($user->payout_address ? "<code>{$this->esc($user->payout_address)}</code>" : 'non renseigné') . "\n" .
            "Conditions : " . ($user->hasAcceptedTerms() ? 'acceptées' : 'non acceptées') . "\n"
        );
    }

    private function sendHelp(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Commandes MobileCrypto</b>\n" .
            "/register NOM | TEL | PAYS — créer le compte\n" .
            "/setwallet 0x... — wallet USDT Polygon\n" .
            "/accept — conditions\n" .
            "/profile — état du compte\n" .
            "/wave — aide Wave\n" .
            "/wavebuy 300 — acheter USDT avec XOF\n" .
            "/wavesell 1 0758431653 — vendre USDT contre XOF\n" .
            "/wavepaid REF ID — preuve paiement Wave\n" .
            "/wavetx REF HASH — preuve dépôt USDT\n" .
            "/waveorders — historique\n" .
            "/whoami — chat ID\n" .
            "/help — cette aide"
        );
    }

    private function sendWaveHelp(string $chatId, MobileCryptoUser $user): void
    {
        if (! $user->isReady()) { $this->sendProfile($chatId, $user); $this->telegram->sendMessage($chatId, '⏳ Termine le KYC/allowlist/conditions avant /wavebuy ou /wavesell.'); return; }
        $this->telegram->sendMessage($chatId,
            "<b>Wave Côte d’Ivoire — manuel</b>\n\n" .
            "<b>Acheter des USDT avec Wave</b>\n<code>/wavebuy 300</code>\nPaiement XOF au 0758431653, puis <code>/wavepaid REF ID_TRANSACTION</code>.\n\n" .
            "<b>Vendre des USDT contre Wave</b>\n<code>/wavesell 1 0758431653</code>\nDépôt USDT Polygon, puis <code>/wavetx REF HASH_USDT</code>.\n\n" .
            "Taux : 1 USDT = 600 XOF · frais 0,5 % · min 0,5 USDT/300 XOF · max 100 USDT/60 000 XOF."
        );
    }

    private function createWaveBuy(string $chatId, MobileCryptoUser $user, string $rawFiat): void
    {
        if (! $user->isReady()) { $this->sendProfile($chatId, $user); $this->telegram->sendMessage($chatId, '⏳ Compte non prêt (KYC/allowlist/conditions).'); return; }
        if (! $user->payout_address) { $this->telegram->sendMessage($chatId, 'Ajoute ton wallet USDT Polygon : <code>/setwallet 0x...</code>'); return; }
        $fiat = (float) str_replace([' ', ','], ['', '.'], trim($rawFiat));
        if ($fiat <= 0) { $this->telegram->sendMessage($chatId, 'Usage : <code>/wavebuy 300</code>.'); return; }
        try {
            $o = $this->wave->createBuy($user, $fiat, 'wave_ci', 'telegram', $chatId);
            $phone = (string) config('wave.operators.wave_ci.phone', '');
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier le numéro Wave', 'copy_text' => ['text' => $phone]]]]];
            $text = "🌊 <b>Achat USDT via Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $o->fiat_amount)} XOF</b> au :\n<pre>" . $this->esc($phone) . "</pre>\n(Wave Côte d’Ivoire)\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur <code>{$this->esc($user->payout_address)}</code>.\n" .
                "Après paiement : <code>/wavepaid {$this->esc($o->reference)} ID_TRANSACTION</code>\n\n⚠️ Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Wave ACHAT\n{$o->reference}\nMobileCrypto #{$user->id} · {$user->full_name}\n{$o->fiat_amount} XOF → {$o->usdt_amount} USDT");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function createWaveSell(string $chatId, MobileCryptoUser $user, array $args): void
    {
        if (! $user->isReady()) { $this->sendProfile($chatId, $user); $this->telegram->sendMessage($chatId, '⏳ Compte non prêt (KYC/allowlist/conditions).'); return; }
        $usdt = (float) str_replace(',', '.', trim((string) ($args[0] ?? '')));
        $phone = trim((string) ($args[1] ?? ($user->phone ?: '')));
        if ($usdt <= 0 || $phone === '') { $this->telegram->sendMessage($chatId, 'Usage : <code>/wavesell 1 0758431653</code>.'); return; }
        try {
            $o = $this->wave->createSell($user, $usdt, $phone, 'wave_ci', 'telegram', $chatId);
            $addr = (string) $o->deposit_address;
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier l’adresse USDT', 'copy_text' => ['text' => $addr]]]]];
            $text = "🌊 <b>Vente USDT → Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur <b>Polygon</b> à :\n<pre>" . $this->esc($addr) . "</pre>\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->fiat_amount)} XOF</b> sur <code>{$this->esc($phone)}</code>.\n" .
                "Après envoi : <code>/wavetx {$this->esc($o->reference)} HASH_USDT</code>\n\n⚠️ Polygon uniquement. Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Wave VENTE\n{$o->reference}\nMobileCrypto #{$user->id} · {$user->full_name}\n{$o->usdt_amount} USDT → {$o->fiat_amount} XOF\nTel: {$phone}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveFiatProof(string $chatId, MobileCryptoUser $user, array $args): void
    {
        $o = $this->wave->latestOrderForChat($chatId, WaveOrder::DIRECTION_BUY_USDT);
        if (! $o) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave ACHAT en attente.'); return; }
        $ref = trim(implode(' ', $args));
        try {
            $o = $this->wave->submitFiatProof($o, $ref ?: null);
            $this->telegram->sendMessage($chatId, "✅ Preuve enregistrée pour <b>{$o->reference}</b>. Vérification admin.");
            $this->telegram->sendAdmin("🔎 Wave ACHAT à vérifier\n{$o->reference}\nMobileCrypto #{$user->id}\n{$o->fiat_amount} XOF\nPreuve: " . ($ref ?: 'non fournie'));
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveUsdtTx(string $chatId, MobileCryptoUser $user, array $args): void
    {
        $hash = trim((string) ($args[1] ?? $args[0] ?? ''));
        $o = $this->wave->latestOrderForChat($chatId, WaveOrder::DIRECTION_SELL_USDT);
        if (! $o) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave VENTE en attente.'); return; }
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
