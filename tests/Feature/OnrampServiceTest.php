<?php

namespace Tests\Feature;

use App\Models\OnrampOrder;
use App\Models\User;
use App\Services\OnrampService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnrampServiceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $suffix = '1'): User
    {
        return User::create([
            'name' => 'onramp-test-' . $suffix,
            'email' => 'onramp-test-' . $suffix . '@example.test',
            'password' => 'secret123',
            'wallet_address' => '0x' . str_pad($suffix, 40, '0', STR_PAD_LEFT),
            'onramp_allowed' => true,
        ]);
    }

    private function configure(): void
    {
        config([
            'onramp.enabled' => true,
            'onramp.networks.bep20.address' => '0xBEP20TREASURYADDRESS',
            'onramp.networks.polygon.address' => '0xPOLYGONTREASURYADDRESS',
            'onramp.packages.mini' => ['label' => '0.35 AVAXe', 'avaxe' => 0.35],
            'onramp.packages.starter' => ['label' => '1 AVAXe', 'avaxe' => 1.0],
            'onramp.avax_usdt_rate' => 7.13,
            'onramp.max_order_usdt' => 50,
            'onramp.daily_limit_usdt' => 100,
        ]);
    }

    public function test_full_manual_onramp_flow(): void
    {
        $this->configure();
        $service = app(OnrampService::class);
        $user = $this->user();

        $issued = $service->issueLinkCode($user);
        $this->assertNotEmpty($issued['code']);

        $link = $service->linkTelegram($issued['code'], '123456', 'tester');
        $this->assertSame($user->id, $link->user_id);
        $this->assertFalse($link->hasAcceptedTerms());

        $link = $service->acceptTerms($link);
        $this->assertTrue($link->hasAcceptedTerms());

        $order = $service->createOrder($user, 'bep20', 'mini', 'telegram', '123456');
        $this->assertSame(OnrampOrder::STATUS_PENDING_DEPOSIT, $order->status);
        $this->assertSame('2.50000000', $order->usdt_amount);
        $this->assertSame('0.35000000', $order->avaxe_amount);

        $order = $service->submitTx($order, str_repeat('a', 64));
        $this->assertSame(OnrampOrder::STATUS_PENDING_REVIEW, $order->status);
        $this->assertNotNull($order->submitted_at);

        $order = $service->approve($order, $user->id, 'ok');
        $this->assertSame(OnrampOrder::STATUS_APPROVED, $order->status);

        $order = $service->credit($order, str_repeat('b', 64), $user->id);
        $this->assertSame(OnrampOrder::STATUS_CREDITED, $order->status);
        $this->assertSame(str_repeat('b', 64), $order->credit_tx_hash);

        $this->assertSame(4, $order->events()->count());
    }

    public function test_duplicate_usdt_tx_is_rejected(): void
    {
        $this->configure();
        $service = app(OnrampService::class);
        $user = $this->user('2');

        $issued = $service->issueLinkCode($user);
        $link = $service->acceptTerms($service->linkTelegram($issued['code'], '222', null));
        $first = $service->createOrder($link->user, 'bep20', 'starter', 'telegram', '222');
        $second = $service->createOrder($link->user, 'bep20', 'starter', 'telegram', '222');

        $tx = str_repeat('c', 64);
        $service->submitTx($first, $tx);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->submitTx($second, $tx);
    }

    public function test_daily_limit_is_enforced(): void
    {
        $this->configure();
        config(['onramp.daily_limit_usdt' => 10]);
        $service = app(OnrampService::class);
        $user = $this->user('3');
        $issued = $service->issueLinkCode($user);
        $link = $service->acceptTerms($service->linkTelegram($issued['code'], '333', null));

        $service->createOrder($link->user, 'polygon', 'starter', 'telegram', '333');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createOrder($link->user, 'polygon', 'starter', 'telegram', '333');
    }
}
