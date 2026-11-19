<?php

namespace App\Services;

use App\Models\OnrampLink;
use App\Models\OnrampOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bot Telegram de l'on-ramp manuel.
 *
 * Il ne signe AUCUNE transaction et ne détient AUCUNE clé. Il orchestre :
 * liaison compte, création de commande, soumission du hash USDT, revue admin,
 * puis enregistrement du hash de crédit AVAXe après envoi manuel par la
 * trésorerie dédiée.
 */
class OnrampTelegramBot
{
    public function __construct(
        private OnrampService $onramp,
        private OfframpService $offramp,
        private WaveService $wave,
        private TelegramService $telegram
    ) {
    }

    public function handle(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
                return;
            }
            if (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            }
        } catch (Throwable $e) {
            Log::error('[OnrampBot] update failed: ' . $e->getMessage(), ['update' => $update]);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = isset($message['chat']['id']) ? (string) $message['chat']['id'] : null;
        $text = trim((string) ($message['text'] ?? ''));
        $username = $message['from']['username'] ?? null;

        if (! $chatId || $text === '') {
            return;
        }

        [$command, $args] = $this->parseCommand($text);

        if ($command === '/start') {
            $payload = $args[0] ?? '';
            if (str_starts_with($payload, 'link_')) {
                $this->linkChat($chatId, substr($payload, 5), $username);
                return;
            }
            $this->sendWelcome($chatId);
            return;
        }

        if ($command === '/help') {
            $this->sendHelp($chatId);
            return;
        }

        if ($command === '/whoami') {
            $this->telegram->sendMessage($chatId, "🆔 Ton chat ID Telegram : <code>{$this->esc($chatId)}</code>\n\nUtilise ce nombre pour <code>TELEGRAM_ADMIN_IDS</code> (si tu es admin) ou pour l’allowlist.");
            return;
        }

        if ($command === '/link') {
            if (! isset($args[0])) {
                $this->telegram->sendMessage($chatId, 'Usage : <code>/link CODE</code>. Génère le code dans l’application Battlepool.');
                return;
            }
            $this->linkChat($chatId, $args[0], $username);
            return;
        }

        if ($command === '/accept') {
            $link = $this->onramp->linkForTelegramChat($chatId);
            if (! $link) {
                $this->sendNeedLink($chatId);
                return;
            }
            $this->onramp->acceptTerms($link);
            $this->telegram->sendMessage($chatId, '✅ Conditions acceptées. Tu peux maintenant utiliser <code>/buy</code> ou <code>/status</code>.');
            return;
        }

        if ($command === '/buy') {
            $this->sendNetworkPicker($chatId);
            return;
        }

        if ($command === '/sell' || $command === '/offramp') {
            $this->createOfframpOrder($chatId, $args[0] ?? '');
            return;
        }

        if ($command === '/wave') {
            $this->sendWaveHelp($chatId);
            return;
        }
        if ($command === '/wavebuy') {
            $this->createWaveBuy($chatId, $args[0] ?? '');
            return;
        }
        if ($command === '/wavesell') {
            $this->createWaveSell($chatId, $args);
            return;
        }
        if ($command === '/wavepaid') {
            $this->submitWaveFiatProof($chatId, $args);
            return;
        }
        if ($command === '/wavetx') {
            $this->submitWaveUsdtTx($chatId, $args);
            return;
        }
        if ($command === '/waveorders') {
            $this->sendWaveOrders($chatId);
            return;
        }

        if ($command === '/tx') {
            $this->submitTx($chatId, $args[0] ?? '');
            return;
        }

        if ($command === '/status') {
            $this->sendUserStatus($chatId);
            return;
        }

        // --- Commandes admin ---
        if (in_array($command, ['/orders', '/approve', '/reject', '/credit', '/allow', '/disallow'], true)) {
            if (! $this->telegram->isAdmin($chatId)) {
                $this->telegram->sendMessage($chatId, '⛔ Commande réservée aux administrateurs.');
                return;
            }
            $this->handleAdminCommand($command, $args, $chatId);
            return;
        }

        $this->telegram->sendMessage($chatId, "Commande inconnue. /help pour la liste.");
    }

    private function handleCallback(array $callback): void
    {
        $id = (string) ($callback['id'] ?? '');
        $chatId = isset($callback['message']['chat']['id']) ? (string) $callback['message']['chat']['id'] : null;
        $data = (string) ($callback['data'] ?? '');

        if (! $chatId) {
            return;
        }

        try {
            if ($data === 'terms_accept') {
                $link = $this->onramp->linkForTelegramChat($chatId);
                if (! $link) {
                    $this->sendNeedLink($chatId);
                    $this->telegram->answerCallbackQuery($id, 'Lie d’abord ton compte avec /link CODE');
                    return;
                }
                $this->onramp->acceptTerms($link);
                $this->telegram->answerCallbackQuery($id, 'Conditions acceptées');
                $this->telegram->sendMessage($chatId, '✅ Conditions acceptées. Utilise <code>/buy</code> pour choisir un réseau.');
                return;
            }

            if (str_starts_with($data, 'net:')) {
                $this->sendPackagePicker($chatId, substr($data, 4));
                $this->telegram->answerCallbackQuery($id);
                return;
            }

            if (str_starts_with($data, 'pkg:')) {
                $parts = explode(':', $data, 3);
                if (count($parts) === 3) {
                    $this->createOrder($chatId, $parts[1], $parts[2]);
                }
                $this->telegram->answerCallbackQuery($id);
                return;
            }

            if (str_starts_with($data, 'approve:') || str_starts_with($data, 'reject:')) {
                if (! $this->telegram->isAdmin($chatId)) {
                    $this->telegram->answerCallbackQuery($id, 'Admin uniquement');
                    return;
                }
                [$action, $orderId] = explode(':', $data, 2);
                $order = OnrampOrder::find((int) $orderId);
                if (! $order) {
                    $this->telegram->answerCallbackQuery($id, 'Commande introuvable');
                    return;
                }
                if ($action === 'approve') {
                    $this->approveOrder($order, $chatId);
                } else {
                    $this->rejectOrder($order, $chatId, 'Refusée via bouton admin');
                }
                $this->telegram->answerCallbackQuery($id);
                return;
            }

            if (str_starts_with($data, 'credit:')) {
                if (! $this->telegram->isAdmin($chatId)) {
                    $this->telegram->answerCallbackQuery($id, 'Admin uniquement');
                    return;
                }
                $order = OnrampOrder::find((int) substr($data, 7));
                if ($order) {
                    $this->telegram->sendMessage($chatId, "Pour enregistrer le crédit AVAXe de <b>{$order->reference}</b>, envoie :\n<code>/credit {$order->id} &lt;HASH_TX_AVAXE&gt;</code>\n\n⚠️ Envoie d’abord les AVAXe depuis la trésorerie dédiée.");
                }
                $this->telegram->answerCallbackQuery($id);
                return;
            }
        } catch (ValidationException $e) {
            $this->telegram->answerCallbackQuery($id, $this->firstError($e));
        } catch (Throwable $e) {
            Log::error('[OnrampBot] callback failed: ' . $e->getMessage());
            $this->telegram->answerCallbackQuery($id, 'Erreur interne.');
        }
    }

    private function linkChat(string $chatId, string $code, ?string $username): void
    {
        try {
            $link = $this->onramp->linkTelegram($code, $chatId, $username);
            $user = $link->user;
            $this->telegram->sendMessage(
                $chatId,
                "✅ Compte lié : <b>#" . (int) $user->id . "</b> (" . $this->shortWallet((string) $user->wallet_address) . ").\n\n" .
                "Avant tout achat, accepte les conditions avec <code>/accept</code>."
            );
        } catch (ValidationException $e) {
            $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e));
        }
    }

    private function sendWelcome(string $chatId): void
    {
        $terms = (string) config('onramp.terms_url', '');
        $termsLine = $terms !== '' ? "Conditions : {$this->esc($terms)}\n\n" : '';
        $keyboard = ['inline_keyboard' => [[['text' => '✅ J’accepte les conditions', 'callback_data' => 'terms_accept']]]];

        $this->telegram->sendMessage(
            $chatId,
            "<b>Battlepool — achat d’AVAXe de test</b>\n\n" .
            "Ce service permet de payer en <b>USDT</b> et de recevoir des <b>AVAXe de test</b> sur Pingala.\n" .
            "⚠️ Les AVAXe de test n’ont pas de valeur réelle et ne sont pas remboursables. " .
            "Les dépôts USDT sont vérifiés manuellement et les crédits sont limités à l’allowlist.\n\n" .
            $termsLine .
            "1. Génère un code de liaison dans l’application Battlepool.\n" .
            "2. Envoie <code>/link TON_CODE</code>.\n" .
            "3. Envoie <code>/accept</code>, puis <code>/buy</code>.\n\n" .
            "<code>/help</code> pour l’aide.",
            $keyboard
        );
    }

    private function sendHelp(string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "<b>Commandes</b>\n" .
            "/start — démarrer / conditions\n" .
            "/link CODE — lier le compte Battlepool\n" .
            "/accept — accepter les conditions\n" .
            "/buy — acheter des AVAXe de test avec USDT\n" .
            "/sell MONTANT — vendre des AVAXe contre USDT (ex: /sell 0.1)\n" .
            "/wave — Wave Côte d’Ivoire (XOF ↔ USDT, manuel)\n" .
            "/tx HASH — soumettre le hash du dépôt USDT\n" .
            "/status — état de tes commandes\n" .
            "/whoami — ton chat ID Telegram\n" .
            "/help — cette aide"
        );
    }

    private function sendNetworkPicker(string $chatId): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }
        if (! $link->hasAcceptedTerms()) {
            $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.');
            return;
        }
        if (! (bool) config('onramp.enabled', false)) {
            $this->telegram->sendMessage($chatId, '⏸️ L’on-ramp est temporairement désactivé.');
            return;
        }
        if (! $link->user->onramp_allowed) {
            $this->telegram->sendMessage($chatId, '⛔ Ton compte n’est pas encore autorisé à l’on-ramp. Contacte l’admin.');
            return;
        }

        $rows = [];
        foreach ((array) config('onramp.networks', []) as $key => $network) {
            if (trim((string) ($network['address'] ?? '')) === '' && $this->onramp->depositAddressPoolSize() === 0) {
                continue;
            }
            $rows[] = [['text' => (string) ($network['label'] ?? $key), 'callback_data' => 'net:' . $key]];
        }
        if (! $rows) {
            $this->telegram->sendMessage($chatId, 'Aucun réseau USDT n’est configuré pour le moment.');
            return;
        }

        $this->telegram->sendMessage($chatId, 'Choisis le réseau USDT du dépôt :', ['inline_keyboard' => $rows]);
    }

    private function sendPackagePicker(string $chatId, string $network): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }
        $networkConfig = config("onramp.networks.{$network}");
        if (! is_array($networkConfig)) {
            $this->telegram->sendMessage($chatId, 'Réseau inconnu.');
            return;
        }

        $rows = [];
        foreach ($this->onramp->packages() as $code => $package) {
            $label = (string) ($package['label'] ?? $code);
            $rows[] = [['text' => $this->fmt((float) $package['usdt']) . ' USDT → ' . $this->fmt((float) $package['avaxe']) . ' AVAXe', 'callback_data' => "pkg:{$network}:{$code}"]];
        }
        if (! $rows) {
            $this->telegram->sendMessage($chatId, 'Aucun package configuré.');
            return;
        }

        $networkLabel = (string) ($networkConfig['label'] ?? $network);
        $this->telegram->sendMessage($chatId, "Réseau : <b>{$this->esc($networkLabel)}</b>\nChoisis le montant USDT :", ['inline_keyboard' => $rows]);
    }

    private function createOrder(string $chatId, string $network, string $packageCode): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }

        try {
            $order = $this->onramp->createOrder($link->user, $network, $packageCode, 'telegram', $chatId);
            $networkConfig = config("onramp.networks.{$network}", []);
            $explorer = (string) ($networkConfig['explorer'] ?? '');
            $label = (string) ($networkConfig['label'] ?? $network);
            $usdt = (string) ($order->usdt_amount);
            $avaxe = (string) ($order->avaxe_amount);

            $keyboard = ['inline_keyboard' => [[
                ['text' => '📋 Copier l’adresse', 'copy_text' => ['text' => $order->deposit_address]],
            ]]];

            $text = "🧾 <b>{$this->esc($order->reference)}</b>\n" .
                "<b>{$usdt} USDT</b> · {$this->esc($label)}\n\n" .
                "Adresse de dépôt :\n<pre>{$this->esc($order->deposit_address)}</pre>\n" .
                "Envoie <b>exactement {$usdt} USDT</b> sur ce réseau.\n" .
                "Détection automatique après confirmations.\n\n" .
                "⚠️ Ne pas envoyer sur un autre réseau.";

            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) {
                // Fallback for clients / Bot API versions without copy_text.
                $this->telegram->sendMessage($chatId, $text);
            }
        } catch (ValidationException $e) {
            $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e));
        }
    }

    private function sendWaveHelp(string $chatId): void
    {
        $this->telegram->sendMessage($chatId,
            "<b>Wave Côte d’Ivoire → USDT / USDT → Wave</b>\n\n" .
            "MVP manuel, validation admin.\n\n" .
            "<b>Acheter des USDT avec Wave</b>\n" .
            "<code>/wavebuy 300</code> — paie 300 XOF, reçois des USDT Polygon.\n" .
            "Puis <code>/wavepaid REF ID_TRANSACTION</code>.\n\n" .
            "<b>Vendre des USDT contre Wave</b>\n" .
            "<code>/wavesell 1 0758431653</code> — envoie 1 USDT Polygon, reçois des XOF.\n" .
            "Puis <code>/wavetx REF HASH_USDT</code>.\n\n" .
            "<code>/waveorders</code> — tes commandes Wave.\n" .
            "Taux : 1 USDT = 600 XOF · frais 0.5 % · min 0.5 USDT/300 XOF · max 100 USDT/60 000 XOF.\n" .
            "KYC et allowlist obligatoires."
        );
    }

    private function createWaveBuy(string $chatId, string $rawFiat): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) return;
        if (! $link->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.'); return; }
        $fiat = (float) str_replace([' ', ','], ['', '.'], trim($rawFiat));
        if ($fiat <= 0) { $this->telegram->sendMessage($chatId, "Usage : <code>/wavebuy 300</code> (montant en XOF)."); return; }
        try {
            $order = $this->wave->createBuy($link->user, $fiat, 'wave_ci', 'telegram', $chatId);
            $phone = (string) config('wave.operators.wave_ci.phone', '');
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier le numéro Wave', 'copy_text' => ['text' => $phone]]]]];
            $text = "🌊 <b>Achat USDT via Wave</b>\n" .
                "Commande <b>{$this->esc($order->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $order->fiat_amount)} XOF</b> au :\n<pre>" . $this->esc($phone) . "</pre>\n" .
                "(Wave Côte d’Ivoire)\n\n" .
                "Tu recevras <b>{$this->fmt((float) $order->usdt_amount)} USDT</b> sur Polygon.\n" .
                "Après paiement : <code>/wavepaid {$this->esc($order->reference)} ID_TRANSACTION</code>\n\n" .
                "⚠️ Indique la référence <b>{$this->esc($order->reference)}</b> si possible. Validation manuelle.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Nouvelle commande Wave ACHAT\n{$order->reference}\nUser #{$order->user_id}\n{$order->fiat_amount} XOF → {$order->usdt_amount} USDT");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function createWaveSell(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) return;
        if (! $link->hasAcceptedTerms()) { $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.'); return; }
        $usdt = (float) str_replace(',', '.', trim((string) ($args[0] ?? '')));
        $phone = trim((string) ($args[1] ?? ''));
        if ($usdt <= 0 || $phone === '') { $this->telegram->sendMessage($chatId, "Usage : <code>/wavesell 1 0758431653</code> (USDT + numéro Wave)."); return; }
        try {
            $order = $this->wave->createSell($link->user, $usdt, $phone, 'wave_ci', 'telegram', $chatId);
            $addr = (string) $order->deposit_address;
            $keyboard = ['inline_keyboard' => [[['text' => '📋 Copier l’adresse USDT', 'copy_text' => ['text' => $addr]]]]];
            $text = "🌊 <b>Vente USDT → Wave</b>\n" .
                "Commande <b>{$this->esc($order->reference)}</b>\n\n" .
                "Envoie <b>{$this->fmt((float) $order->usdt_amount)} USDT</b> sur <b>Polygon</b> à :\n<pre>" . $this->esc($addr) . "</pre>\n\n" .
                "Tu recevras <b>{$this->fmt((float) $order->fiat_amount)} XOF</b> sur <code>{$this->esc($phone)}</code>.\n" .
                "Après envoi : <code>/wavetx {$this->esc($order->reference)} HASH_USDT</code>\n\n" .
                "⚠️ Envoie uniquement sur Polygon. Validation manuelle du dépôt puis paiement Wave.";
            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) $this->telegram->sendMessage($chatId, $text);
            $this->telegram->sendAdmin("🌊 Nouvelle commande Wave VENTE\n{$order->reference}\nUser #{$order->user_id}\n{$order->usdt_amount} USDT → {$order->fiat_amount} XOF\nTel: {$phone}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveFiatProof(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) return;
        $order = $this->wave->latestOrderForChat($chatId, \App\Models\WaveOrder::DIRECTION_BUY_USDT);
        if (! $order) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave ACHAT en attente. Utilise <code>/wavebuy 300</code>.'); return; }
        $ref = trim(implode(' ', $args));
        try {
            $order = $this->wave->submitFiatProof($order, $ref ?: null);
            $this->telegram->sendMessage($chatId, "✅ Preuve de paiement enregistrée pour <b>{$order->reference}</b>. Un admin vérifie le paiement Wave.");
            $this->telegram->sendAdmin("🔎 Wave ACHAT à vérifier\n{$order->reference}\nUser #{$order->user_id}\n{$order->fiat_amount} XOF\nPreuve: " . ($ref ?: 'non fournie'));
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function submitWaveUsdtTx(string $chatId, array $args): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) return;
        $hash = trim((string) ($args[1] ?? $args[0] ?? ''));
        $order = $this->wave->latestOrderForChat($chatId, \App\Models\WaveOrder::DIRECTION_SELL_USDT);
        if (! $order) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave VENTE en attente. Utilise <code>/wavesell 1 0758431653</code>.'); return; }
        try {
            $order = $this->wave->submitUsdtTx($order, $hash);
            $this->telegram->sendMessage($chatId, "✅ Dépôt USDT soumis pour <b>{$order->reference}</b>. Un admin vérifie puis paie Wave.");
            $this->telegram->sendAdmin("🔎 Wave VENTE à vérifier\n{$order->reference}\nUser #{$order->user_id}\n{$order->usdt_amount} USDT\nTx: {$order->usdt_deposit_tx_hash}");
        } catch (ValidationException $e) { $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e)); }
    }

    private function sendWaveOrders(string $chatId): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) return;
        $orders = $this->wave->ordersForUser($link->user, 10);
        if ($orders->isEmpty()) { $this->telegram->sendMessage($chatId, 'Aucune commande Wave.'); return; }
        $lines = ["<b>Tes commandes Wave</b>"];
        foreach ($orders as $o) {
            $lines[] = "• <code>{$o->reference}</code> — " . ($o->direction === 'buy_usdt' ? "{$o->fiat_amount} XOF → {$o->usdt_amount} USDT" : "{$o->usdt_amount} USDT → {$o->fiat_amount} XOF") . " — <b>{$o->status}</b>";
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function createOfframpOrder(string $chatId, string $rawAmount): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }
        if (! $link->hasAcceptedTerms()) {
            $this->telegram->sendMessage($chatId, 'Accepte d’abord les conditions avec <code>/accept</code>.');
            return;
        }
        $amount = (float) str_replace(',', '.', trim($rawAmount));
        if ($amount <= 0) {
            $this->telegram->sendMessage($chatId, "Usage : <code>/sell 0.1</code> (montant en AVAXe).\nMinimum 0.1 AVAXe, maximum 10 AVAXe.");
            return;
        }

        try {
            $order = $this->offramp->createOrder($link->user, $amount, 'telegram', $chatId);
            $keyboard = ['inline_keyboard' => [[
                ['text' => '📋 Copier l’adresse AVAXe', 'copy_text' => ['text' => $order->deposit_address]],
            ]]];
            $text = "💸 <b>Off-ramp {$this->esc($order->reference)}</b>\n" .
                "<b>{$this->fmt((float) $order->avaxe_amount)} AVAXe</b> → <b>{$this->fmt((float) $order->usdt_amount)} USDT</b> · Polygon\n\n" .
                "Adresse AVAXe de dépôt :\n<pre>{$this->esc($order->deposit_address)}</pre>\n" .
                "Envoie <b>exactement {$this->fmt((float) $order->avaxe_amount)} AVAXe</b> sur Pingala.\n" .
                "USDT envoyé à <code>{$this->esc($order->payout_address)}</code> après confirmations.\n\n" .
                "⚠️ Tu as besoin de <b>TST</b> dans ton wallet pour les frais Pingala.\n" .
                "⚠️ Ne pas envoyer sur un autre réseau.";

            if (! $this->telegram->sendMessage($chatId, $text, $keyboard)) {
                $this->telegram->sendMessage($chatId, $text);
            }
        } catch (ValidationException $e) {
            $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e));
        }
    }

    private function submitTx(string $chatId, string $txHash): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }
        if ($txHash === '') {
            $this->telegram->sendMessage($chatId, 'Usage : <code>/tx HASH_DE_LA_TRANSACTION_USDT</code>');
            return;
        }
        $order = $this->onramp->latestPendingOrderForChat($chatId);
        if (! $order) {
            $this->telegram->sendMessage($chatId, 'Aucune commande en attente de dépôt. Utilise <code>/buy</code>.');
            return;
        }

        try {
            $order = $this->onramp->submitTx($order, $txHash);
            $this->telegram->sendMessage($chatId, "✅ Dépôt soumis pour vérification.\nCommande <b>{$order->reference}</b> en revue manuelle. Tu seras notifié après validation.");

            $networkConfig = config("onramp.networks.{$order->network}", []);
            $explorer = (string) ($networkConfig['explorer'] ?? '');
            $label = (string) ($networkConfig['label'] ?? $order->network);
            $wallet = $this->shortWallet((string) ($order->user->wallet_address ?? ''));
            $keyboard = ['inline_keyboard' => [[
                ['text' => '✅ Approuver', 'callback_data' => 'approve:' . $order->id],
                ['text' => '❌ Refuser', 'callback_data' => 'reject:' . $order->id],
            ]]];

            $this->telegram->sendAdmin(
                "🔎 <b>Commande à vérifier</b>\n\n" .
                "Réf : <b>{$order->reference}</b>\n" .
                "User : #{$order->user_id} ({$wallet})\n" .
                "Réseau : {$this->esc($label)}\n" .
                "Attendu : <b>{$order->usdt_amount} USDT</b> → {$this->fmt((float) $order->avaxe_amount)} AVAXe\n" .
                "Hash : <code>{$this->esc($order->tx_hash)}</code>\n" .
                ($explorer !== '' ? "Explorer : {$this->esc($explorer . $order->tx_hash)}\n" : '') .
                "\nVérifie le montant, le réseau et les confirmations. Ensuite :\n" .
                "<code>/approve {$order->id}</code> puis, après envoi AVAXe :\n" .
                "<code>/credit {$order->id} HASH_AVAXE</code>",
                $keyboard
            );
        } catch (ValidationException $e) {
            $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e));
        }
    }

    private function sendUserStatus(string $chatId): void
    {
        $link = $this->requireLink($chatId);
        if (! $link) {
            return;
        }
        $orders = $this->onramp->ordersForUser($link->user, 8);
        if ($orders->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Aucune commande. Utilise <code>/buy</code>.');
            return;
        }
        $lines = ["<b>Tes commandes</b>"];
        foreach ($orders as $order) {
            $lines[] = "• <code>{$order->reference}</code> — {$order->usdt_amount} USDT — <b>{$this->statusLabel($order->status)}</b>";
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleAdminCommand(string $command, array $args, string $chatId): void
    {
        if ($command === '/orders') {
            $orders = $this->onramp->ordersForAdmin(OnrampOrder::STATUS_PENDING_REVIEW, 20);
            if ($orders->isEmpty()) {
                $this->telegram->sendMessage($chatId, 'Aucune commande en attente de revue.');
                return;
            }
            $lines = ["<b>Commandes en revue</b>"];
            foreach ($orders as $order) {
                $lines[] = "#{$order->id} <code>{$order->reference}</code> — {$order->usdt_amount} USDT — {$order->network} — user #{$order->user_id}";
            }
            $lines[] = "\n/approve ID — /reject ID raison — /credit ID HASH_AVAXE";
            $this->telegram->sendMessage($chatId, implode("\n", $lines));
            return;
        }

        $targetId = isset($args[0]) ? (int) $args[0] : 0;
        if ($targetId <= 0) {
            $this->telegram->sendMessage($chatId, 'Usage : ' . $command . ' ID ...');
            return;
        }

        // /allow et /disallow ciblent un user_id, pas une commande.
        if ($command === '/allow' || $command === '/disallow') {
            $user = User::find($targetId);
            if (! $user) {
                $this->telegram->sendMessage($chatId, 'Utilisateur introuvable.');
                return;
            }
            $allow = $command === '/allow';
            $this->onramp->allowUser($user, $allow);
            $this->telegram->sendMessage($chatId, ($allow ? '✅' : '⛔') . " onramp_allowed=" . ($allow ? 'true' : 'false') . " pour user #{$user->id}.");
            return;
        }

        $order = OnrampOrder::find($targetId);
        if (! $order) {
            $this->telegram->sendMessage($chatId, 'Commande introuvable.');
            return;
        }

        try {
            if ($command === '/approve') {
                $note = isset($args[1]) ? implode(' ', array_slice($args, 1)) : null;
                $this->approveOrder($order, $chatId, $note);
                return;
            }
            if ($command === '/reject') {
                $reason = isset($args[1]) ? implode(' ', array_slice($args, 1)) : 'Refusée';
                $this->rejectOrder($order, $chatId, $reason);
                return;
            }
            if ($command === '/credit') {
                $hash = $args[1] ?? '';
                if ($hash === '') {
                    $this->telegram->sendMessage($chatId, 'Usage : <code>/credit ID HASH_AVAXE</code>');
                    return;
                }
                $order = $this->onramp->credit($order, $hash, $this->adminUserId($chatId));
                $this->notifyOrderUser($order, "✅ <b>AVAXe crédités</b>\nCommande <b>{$order->reference}</b> : {$this->fmt((float) $order->avaxe_amount)} AVAXe envoyés sur <code>{$this->esc((string) $order->user->wallet_address)}</code>.\nTx : <code>{$this->esc((string) $order->credit_tx_hash)}</code>");
                $this->telegram->sendMessage($chatId, "✅ Crédit enregistré pour {$order->reference}.");
                return;
            }
        } catch (ValidationException $e) {
            $this->telegram->sendMessage($chatId, '❌ ' . $this->firstError($e));
        }
    }

    private function approveOrder(OnrampOrder $order, string $adminChatId, ?string $note = null): void
    {
        $order = $this->onramp->approve($order, $this->adminUserId($adminChatId), $note);
        $this->notifyOrderUser($order, "✅ <b>Paiement validé</b>\nCommande <b>{$order->reference}</b>. Le crédit AVAXe est en cours de traitement.");
        $this->telegram->sendMessage(
            $adminChatId,
            "✅ Commande {$order->reference} approuvée.\nEnvoie maintenant <b>{$this->fmt((float) $order->avaxe_amount)} AVAXe</b> depuis la trésorerie à <code>{$this->esc((string) $order->user->wallet_address)}</code>, puis :\n<code>/credit {$order->id} HASH_AVAXE</code>",
            ['inline_keyboard' => [[['text' => 'Saisir le crédit', 'callback_data' => 'credit:' . $order->id]]]]
        );
    }

    private function rejectOrder(OnrampOrder $order, string $adminChatId, string $reason): void
    {
        $order = $this->onramp->reject($order, $this->adminUserId($adminChatId), $reason);
        $this->notifyOrderUser($order, "❌ <b>Commande refusée</b>\nCommande <b>{$order->reference}</b>.\nMotif : {$this->esc($reason)}");
        $this->telegram->sendMessage($adminChatId, "❌ Commande {$order->reference} refusée.");
    }

    private function notifyOrderUser(OnrampOrder $order, string $message): void
    {
        if ($order->chat_id) {
            $this->telegram->sendMessage((string) $order->chat_id, $message);
        }
    }

    private function requireLink(string $chatId): ?OnrampLink
    {
        $link = $this->onramp->linkForTelegramChat($chatId);
        if (! $link) {
            $this->sendNeedLink($chatId);
            return null;
        }
        return $link;
    }

    private function sendNeedLink(string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "🔗 Ton chat n’est pas lié à un compte Battlepool.\n" .
            "Génère un code dans l’application, puis envoie <code>/link TON_CODE</code>."
        );
    }

    private function adminUserId(string $chatId): ?int
    {
        // Les admins Telegram ne sont pas forcément des users Laravel : on garde
        // l’ID Telegram comme acteur, sans inventer un user_id.
        return null;
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
        $errors = $e->errors();
        $first = reset($errors);
        return is_array($first) ? (string) reset($first) : (string) $first;
    }

    private function statusLabel(string $status): string
    {
        return [
            OnrampOrder::STATUS_PENDING_DEPOSIT => 'en attente de dépôt',
            OnrampOrder::STATUS_PENDING_REVIEW => 'en revue',
            OnrampOrder::STATUS_APPROVED => 'approuvée, crédit en cours',
            OnrampOrder::STATUS_CREDITED => 'créditée',
            OnrampOrder::STATUS_REJECTED => 'refusée',
            OnrampOrder::STATUS_EXPIRED => 'expirée',
        ][$status] ?? $status;
    }

    private function shortWallet(string $wallet): string
    {
        return strlen($wallet) > 12 ? substr($wallet, 0, 6) . '…' . substr($wallet, -4) : $wallet;
    }

    private function fmt(float $value): string
    {
        return number_format($value, 6, '.', '');
    }

    private function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
