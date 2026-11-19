<?php

$waveAdminIds = (string) env('TELEGRAM_MOBILECRYPTO_ADMIN_IDS', env('WAVE_TELEGRAM_ADMIN_IDS', env('TELEGRAM_ADMIN_IDS', '')));

return [
    'enabled' => (bool) env('WAVE_ENABLED', true),
    'rate_xof_per_usdt' => (float) env('WAVE_RATE_XOF_PER_USDT', 600),
    'fee_percent' => (float) env('WAVE_FEE_PERCENT', 0.5),
    'min_usdt' => (float) env('WAVE_MIN_USDT', 0.5),
    'max_usdt' => (float) env('WAVE_MAX_USDT', 10),
    'daily_limit_usdt' => (float) env('WAVE_DAILY_LIMIT_USDT', 20),
    'min_xof' => (float) env('WAVE_MIN_XOF', 300),
    'max_xof' => (float) env('WAVE_MAX_XOF', 6000),
    'require_kyc' => (bool) env('WAVE_REQUIRE_KYC', false),
    'payout_network' => (string) env('WAVE_PAYOUT_NETWORK', 'polygon'),
    'portal_webhook_secret' => (string) env('WAVE_PORTAL_WEBHOOK_SECRET', ''),
    'usdt_deposit_address' => (string) env('WAVE_USDT_DEPOSIT_ADDRESS', '0xE4b74c895eA6B5c599AE578859Cce74E9317a40B'),
    'telegram' => [
        'token' => (string) env('TELEGRAM_MOBILECRYPTO_BOT_TOKEN', env('WAVE_TELEGRAM_BOT_TOKEN', '')),
        'webhook_secret' => (string) env('TELEGRAM_MOBILECRYPTO_WEBHOOK_SECRET', env('WAVE_TELEGRAM_WEBHOOK_SECRET', '')),
        'bot_username' => (string) env('TELEGRAM_MOBILECRYPTO_BOT_USERNAME', env('WAVE_TELEGRAM_BOT_USERNAME', 'MobileCrypoXBot')),
        'admin_ids' => array_values(array_filter(array_map('trim', explode(',', $waveAdminIds)))),
    ],

    'operators' => [
        'wave_ci' => [
            'label' => 'Wave Côte d’Ivoire',
            'country' => 'CI',
            'currency' => 'XOF',
            'payment_link_base' => (string) env('WAVE_CI_PAYMENT_LINK_BASE', 'https://pay.wave.com/m/M_ci_Orgg9myp4tUY/c/ci/'),
            'enabled' => true,
        ],
    ],
];
