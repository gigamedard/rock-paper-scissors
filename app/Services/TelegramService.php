<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private array $adminIds;

    public function __construct()
    {
        $this->token = trim((string) config('onramp.telegram.token', ''));
        $this->adminIds = array_values(array_filter(array_map('strval', (array) config('onramp.telegram.admin_ids', []))));
    }

    public function enabled(): bool
    {
        return $this->token !== '';
    }

    public function adminIds(): array
    {
        return $this->adminIds;
    }

    public function isAdmin(?string $chatId): bool
    {
        return $chatId !== null && in_array((string) $chatId, $this->adminIds, true);
    }

    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        if (! $this->enabled()) {
            Log::warning('[Telegram] token absent — message non envoyé', ['chat_id' => $chatId]);
            return false;
        }
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990) . '…';
        }
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if ($replyMarkup !== null) {
                $payload['reply_markup'] = $replyMarkup;
            }
            $res = Http::timeout(10)->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
            if (! $res->successful()) {
                Log::warning('[Telegram] sendMessage échec', ['status' => $res->status(), 'body' => $res->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[Telegram] sendMessage exception: ' . $e->getMessage());
            return false;
        }
    }

    public function sendAdmin(string $text, ?array $replyMarkup = null): int
    {
        $sent = 0;
        foreach ($this->adminIds as $chatId) {
            if ($this->sendMessage($chatId, $text, $replyMarkup)) {
                $sent++;
            }
        }
        return $sent;
    }

    public function answerCallbackQuery(string $callbackId, ?string $text = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$this->token}/answerCallbackQuery", array_filter([
                'callback_query_id' => $callbackId,
                'text' => $text,
            ], fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::warning('[Telegram] answerCallbackQuery: ' . $e->getMessage());
        }
    }

    public function setWebhook(string $url): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN absent'];
        }
        try {
            $res = Http::timeout(15)->post("https://api.telegram.org/bot{$this->token}/setWebhook", [
                'url' => $url,
                'secret_token' => (string) config('onramp.telegram.webhook_secret', ''),
                'allowed_updates' => ['message', 'callback_query'],
            ]);
            return $res->json() ?: ['ok' => false, 'status' => $res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
