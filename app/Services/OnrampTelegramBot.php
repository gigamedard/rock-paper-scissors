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
            "/tx HASH — soumettre le hash du dépôt USDT\n" .
            "/status — état de tes commandes\n" .
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
            if (trim((string) ($network['address'] ?? '')) === '') {
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
        foreach ((array) config('onramp.packages', []) as $code => $package) {
            $label = (string) ($package['label'] ?? $code);
            $rows[] = [['text' => $label . ' → ' . $this->fmt((float) ($package['avaxe'] ?? 0)) . ' AVAXe', 'callback_data' => "pkg:{$network}:{$code}"]];
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

            $this->telegram->sendMessage(
                $chatId,
                "🧾 <b>Commande {$this->esc($order->reference)}</b>\n\n" .
                "Réseau : <b>{$this->esc($label)}</b>\n" .
                "Montant à envoyer : <b>{$usdt} USDT</b>\n" .
                "Adresse de dépôt :\n<code>{$this->esc($order->deposit_address)}</code>\n\n" .
                "Tu recevras <b>{$this->fmt((float) $avaxe)} AVAXe de test</b> après vérification.\n\n" .
                "Envoie <b>exactement {$usdt} USDT</b> sur ce réseau, puis le hash avec :\n" .
                "<code>/tx TON_HASH</code>\n\n" .
                "⚠️ Ne pas envoyer sur un autre réseau. Les frais de réseau restent à ta charge."
            );
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
