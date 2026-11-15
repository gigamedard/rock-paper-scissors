<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnrampTelegramWebhookTest extends TestCase
{
    public function test_webhook_rejects_invalid_secret(): void
    {
        config([
            'onramp.telegram.token' => 'test-token',
            'onramp.telegram.webhook_secret' => 'expected-secret',
            'onramp.telegram.admin_ids' => [],
        ]);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start']], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong',
        ])->assertStatus(403);
    }

    public function test_webhook_accepts_start_with_valid_secret(): void
    {
        config([
            'onramp.telegram.token' => 'test-token',
            'onramp.telegram.webhook_secret' => 'expected-secret',
            'onramp.telegram.bot_username' => 'BattlepoolTestBot',
            'onramp.telegram.admin_ids' => [],
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 123, 'type' => 'private'],
                'from' => ['id' => 123, 'username' => 'tester'],
                'text' => '/start',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'expected-secret',
        ])->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage'));
    }
}
