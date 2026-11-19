<?php

return [
    'enabled' => (bool) env('WAVE_ENABLED', true),
    'rate_xof_per_usdt' => (float) env('WAVE_RATE_XOF_PER_USDT', 600),
    'fee_percent' => (float) env('WAVE_FEE_PERCENT', 0.5),
    'min_usdt' => (float) env('WAVE_MIN_USDT', 0.5),
    'max_usdt' => (float) env('WAVE_MAX_USDT', 100),
    'daily_limit_usdt' => (float) env('WAVE_DAILY_LIMIT_USDT', 500),
    'min_xof' => (float) env('WAVE_MIN_XOF', 300),
    'max_xof' => (float) env('WAVE_MAX_XOF', 60000),
    'require_kyc' => (bool) env('WAVE_REQUIRE_KYC', true),
    'payout_network' => (string) env('WAVE_PAYOUT_NETWORK', 'polygon'),
    'usdt_deposit_address' => (string) env('WAVE_USDT_DEPOSIT_ADDRESS', '0xE4b74c895eA6B5c599AE578859Cce74E9317a40B'),
    'telegram' => [
        'token' => (string) env('WAVE_TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => (string) env('WAVE_TELEGRAM_WEBHOOK_SECRET', ''),
        'bot_username' => (string) env('WAVE_TELEGRAM_BOT_USERNAME', 'MobileCryptoXBot'),
        'admin_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('WAVE_TELEGRAM_ADMIN_IDS', env('TELEGRAM_ADMIN_IDS', '')))))),
    ],

    'operators' => [
        'wave_ci' => [
            'label' => 'Wave Côte d’Ivoire',
            'country' => 'CI',
            'currency' => 'XOF',
            'phone' => (string) env('WAVE_CI_PHONE', '0758431653'),
            'account_name' => (string) env('WAVE_CI_ACCOUNT_NAME', 'BattlePool'),
            'enabled' => true,
        ],
    ],
];
