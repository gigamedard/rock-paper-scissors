<?php

namespace App\Services;

use App\Models\OnrampLink;
use App\Models\WaveOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Bot Telegram dédié MobileCrypto (Wave XOF ↔ USDT, MVP manuel). */
class WaveTelegramBot
{
    public function __construct(
        private OnrampService $onramp,
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

        [$command, $args] = $this->parseCommand($text);

        if ($command === '/start') {
            $payload = $args[0] ?? '';
            if (str_starts_with($payload, 'link_')) { $this->linkChat($chatId, substr($payload, 5), $username); return; }
            $this->sendWelcome($chatId);
            return;
        }
        if ($command === '/whoami') {
            $this->telegram->sendMessage($chatId, "🆔 Chat ID Telegram : <code>{$this->esc($chatId)}</code>");
            return;
        }
        if ($command === '/link') {
            if (! isset($args[0])) { $this->telegram->sendMessage($chatId, 'Usage : <code>/link CODE</code>.'); return; }
            $this->linkChat($chatId, $args[0], $username);
            return;
        }
        if ($command === '/accept') {
            $link = $this->onramp->linkForTelegramChat($chatId);
            if (! $link) { $this->sendNeedLink($chatId); return; }
            $this->onramp->acceptTerms($link);
            $this->telegram->sendMessage($chatId, '✅ Conditions acceptées. Utilise <code>/wave</code>.');
            return;
        }
        if ($command === '/wave') { $this->sendWaveHelp($chatId); return; }
        if ($command === '/wavebuy') { $this->createWaveBuy($chatId, $args[0] ?? ''); return; }
        if ($command === '/wavesell') { $this->createWaveSell($chatId, $args); return; }
        if ($command === '/wavepaid') { $this->submitWaveFiatProof($chatId, $args); return; }
        if ($command === '/wavetx') { $this->submitWaveUsdtTx($chatId, $args); return; }
        if ($command === '/waveorders') { $this->sendWaveOrders($chatId); return; }
        if ($command === '/help') { $this->sendHelp($chatId); return; }
        $this->telegram->sendMessage($chatId, 'Commande inconnue. Utilise <code>/help</code>.');
    }

    private function handleCallback(array $callback): void
    {
        $id = (string) ($callback['id'] ?? '');
        $chatId = isset($callback['message']['chat']['id']) ? (string) $callback['message']['chat']['id'] : null;
        $data = (string) ($callback['data'] ?? '');
        if (! $chatId) return;
        if ($data === 'terms_accept') {
            $link = $this->onramp->linkForTelegramChat($chatId);
            if (! $link) { $this->sendNeedLink($chatId); $this->telegram->answerCallbackQuery($id, 'Lie ton compte avec /link CODE'); return; }
            $this->onramp->acceptTerms($link);
            $this->telegram->answerCallbackQuery($id, 'Conditions acceptées');
            $this->telegram->sendMessage($chatId, '✅ Conditions acceptées. Utilise <code>/wave</code>.');
        }
    }

    private function sendWelcome(string $chatId): void
    {
        $terms = (string) config('onramp.terms_url', '');
        $keyboard = ['inline_keyboard' => [[['text' => '✅ J’accepte les conditions', 'callback_data' => 'terms_accept']]]];
        $this->telegram->sendMessage($chatId,
            "<b>MobileCrypto — Wave ↔ USDT</b>\n\n" .
            "Service manuel Wave Côte d’Ivoire ↔ USDT Polygon.\n" .
            "KYC et allowlist obligatoires. Validation admin.\n\n" .
            ($terms !== '' ? "Conditions : {$this->esc($terms)}\n\n" : '') .
            "1. Génère un code de liaison dans BattlePool.\n" .
            "2. Envoie <code>/link CODE</code>.\n" .
            "3. <code>/accept</code>, puis <code>/wave</code>.\n\n" .
            "<code>/help</code> pour l’aide.",
            $keyboard
        );
    }

    private function sendHelp(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Commandes MobileCrypto</b>\n" .
            "/link CODE — lier le compte BattlePool\n" .
            "/accept — accepter les conditions\n" .
            "/wave — aide Wave\n" .
            "/wavebuy 300 — acheter des USDT avec 300 XOF\n" .
            "/wavesell 1 0758431653 — vendre 1 USDT contre des XOF\n" .
            "/wavepaid REF ID — preuve paiement Wave\n" .
            "/wavetx REF HASH — preuve dépôt USDT\n" .
            "/waveorders — historique\n" .
            "/whoami — chat ID\n" .
            "/help — cette aide"
        );
    }

    private function sendWaveHelp(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Wave Côte d’Ivoire — manuel</b>\n\n" .
            "<b>Acheter des USDT avec Wave</b>\n" .
            "<code>/wavebuy 300</code>\n" .
            "Paiement XOF au 0758431653, puis <code>/wavepaid REF ID_TRANSACTION</code>.\n\n" .
            "<b>Vendre des USDT contre Wave</b>\n" .
            "<code>/wavesell 1 0758431653</code>\n" .
            "Dépôt USDT Polygon, puis <code>/wavetx REF HASH_USDT</code>.\n\n" .
            "Taux : 1 USDT = 600 XOF · frais 0,5 % · min 0,5 USDT/300 XOF · max 100 USDT/60 000 XOF.\n" .
            "KYC et allowlist obligatoires. Validation manuelle."
        );
    }

    private function createWaveBuy(string $chatId, string $rawFiat): void
    {
        $link = $this->requireLink($chatId); if (! $link) return;
        if (! $link->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.'); return; }
        $fiat = (float) str_replace([' ', ','], ['', '.'], trim($rawFiat));
        if ($fiat <= 0) { $this->telegram->sendMessage($chatId, 'Usage : <code>/wavebuy 300</code>.'); return; }
        try {
            $o = $this->wave->createBuy($link->user, $fiat, 'wave_ci', 'telegram', $chatId);
            $phone = (string) config('wave.operators.wave_ci.phone', '');
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier le numéro Wave', 'copy_text' => ['text' => $phone]]]]];
            $text = "🌊 <b>Achat USDT via Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $o->fiat_amount)} XOF</b> au :\n<pre>" . $this->esc($phone) . "</pre>\n" .
                "(Wave Côte d’Ivoire)\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur Polygon.\n" .
                "Après paiement : <code>/wavepaid {$this->esc($o->reference)} ID_TRANSACTION</code>\n\n" .
                "⚠️ Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Wave ACHAT\n{$o->reference}\nUser #{$o->user_id}\n{$o->fiat_amount} XOF → {$o->usdt_amount} USDT");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function createWaveSell(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId); if (! $link) return;
        if (! $link->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.'); return; }
        $usdt = (float) str_replace(',', '.', trim((string) ($args[0] ?? '')));
        $phone = trim((string) ($args[1] ?? ''));
        if ($usdt <= 0 || $phone === '') { $this->telegram->sendMessage($chatId, 'Usage : <code>/wavesell 1 0758431653</code>.'); return; }
        try {
            $o = $this->wave->createSell($link->user, $usdt, $phone, 'wave_ci', 'telegram', $chatId);
            $addr = (string) $o->deposit_address;
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier l’adresse USDT', 'copy_text' => ['text' => $addr]]]]];
            $text = "🌊 <b>Vente USDT → Wave</b>\nCommande <b>{$this->esc($o->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $o->usdt_amount)} USDT</b> sur <b>Polygon</b> à :\n<pre>" . $this->esc($addr) . "</pre>\n\n" .
                "Tu recevras <b>{$this->fmt((float) $o->fiat_amount)} XOF</b> sur <code>{$this->esc($phone)}</code>.\n" .
                "Après envoi : <code>/wavetx {$this->esc($o->reference)} HASH_USDT</code>\n\n" .
                "⚠️ Polygon uniquement. Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Wave VENTE\n{$o->reference}\nUser #{$o->user_id}\n{$o->usdt_amount} USDT → {$o->fiat_amount} XOF\nTel: {$phone}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveFiatProof(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId); if (! $link) return;
        $o = $this->wave->latestOrderForChat($chatId, WaveOrder::DIRECTION_BUY_USDT);
        if (! $o) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave ACHAT en attente.'); return; }
        $ref = trim(implode(' ', $args));
        try {
            $o = $this->wave->submitFiatProof($o, $ref ?: null);
            $this->telegram->sendMessage($chatId, "✅ Preuve enregistrée pour <b>{$o->reference}</b>. Vérification admin.");
            $this->telegram->sendAdmin("🔎 Wave ACHAT à vérifier\n{$o->reference}\nUser #{$o->user_id}\n{$o->fiat_amount} XOF\nPreuve: " . ($ref ?: 'non fournie'));
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveUsdtTx(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId); if (! $link) return;
        $hash = trim((string) ($args[1] ?? $args[0] ?? ''));
        $o = $this->wave->latestOrderForChat($chatId, WaveOrder::DIRECTION_SELL_USDT);
        if (! $o) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave VENTE en attente.'); return; }
        try {
            $o = $this->wave->submitUsdtTx($o, $hash);
            $this->telegram->sendMessage($chatId, "✅ Dépôt USDT soumis pour <b>{$o->reference}</b>. Vérification admin.");
            $this->telegram->sendAdmin("🔎 Wave VENTE à vérifier\n{$o->reference}\nUser #{$o->user_id}\n{$o->usdt_amount} USDT\nTx: {$o->usdt_deposit_tx_hash}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function sendWaveOrders(string $chatId): void
    {
        $link = $this->requireLink($chatId); if (! $link) return;
        $orders = $this->wave->ordersForUser($link->user, 10);
        if ($orders->isEmpty()) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave.'); return; }
        $lines = ['<b>Tes commandes Wave</b>'];
        foreach ($orders as $o) {
            $lines[] = "• <code>{$o->reference}</code> — " . ($o->direction === 'buy_usdt' ? "{$o->fiat_amount} XOF → {$o->usdt_amount} USDT" : "{$o->usdt_amount} USDT → {$o->fiat_amount} XOF") . " — <b>{$o->status}</b>";
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function linkChat(string $chatId, string $code, ?string $username): void
    {
        try {
            $link = $this->onramp->linkTelegram($code, $chatId, $username);
            $this->telegram->sendMessage($chatId, "✅ Compte lié : <b>#" . (int) $link->user_id . "</b>.\nAccepte les conditions avec <code>/accept</code>, puis <code>/wave</code>.");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function requireLink(string $chatId): ?OnrampLink
    {
        $link = $this->onramp->linkForTelegramChat($chatId);
        if (! $link) { $this->sendNeedLink($chatId); return null; }
        return $link;
    }

    private function sendNeedLink(string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "🔗 Chat non lié.\nGénère un code dans BattlePool, puis <code>/link CODE</code>.");
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
