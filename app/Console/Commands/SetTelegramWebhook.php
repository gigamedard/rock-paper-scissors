<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'onramp:telegram-set-webhook {--url= : URL publique du webhook}';
    protected $description = 'Configure le webhook Telegram de l’on-ramp';

    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN absent.');
            return self::FAILURE;
        }
        $secret = (string) config('onramp.telegram.webhook_secret', '');
        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET absent.');
            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/') . '/api/telegram/webhook');
        $result = $telegram->setWebhook($url);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return ! empty($result['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
