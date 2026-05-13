<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class UserTracker
{
    private const TRACKED_ADDRESSES = [
        '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266',
        '0x70997970c51812dc3a010c7d01b50e0d17dc79c8',
        '0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc',
        '0x90f79bf6eb2c4f870365e785982e1f101e93b906',
        '0x15d34aaf54267db7d7c367839aaf71a00a2c6a65',
        '0x9965507d1a55bcc2695c58ba16fb37d819b0a4dc',
    ];

    public static function isTracked(?string $address): bool
    {
        if (! $address) {
            return false;
        }

        return in_array(strtolower($address), self::TRACKED_ADDRESSES, true);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $channel = 'tracked_users';

        $address = $context['address'] ?? $context['wallet_address'] ?? $context['wallet'] ?? null;
        if ($address && ! self::isTracked($address)) {
            $channel = 'other_users';
        }

        Log::channel($channel)->$level($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }
}
