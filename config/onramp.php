<?php

/**
 * On-ramp USDT → AVAXe (MVP manuel, allowlist).
 *
 * Les adresses de dépôt et le token Telegram sont fournis via l'environnement.
 * ONRAMP_ENABLED=false par défaut : aucune commande ne peut être créée tant que
 * les adresses et les conditions ne sont pas validées.
 */
return [
    'enabled' => (bool) env('ONRAMP_ENABLED', false),
    'terms_version' => env('ONRAMP_TERMS_VERSION', 'v1'),
    'terms_url' => env('ONRAMP_TERMS_URL', ''),
    'link_code_ttl_minutes' => (int) env('ONRAMP_LINK_CODE_TTL_MINUTES', 15),
    'daily_limit_usdt' => (float) env('ONRAMP_DAILY_LIMIT_USDT', 100),
    'max_order_usdt' => (float) env('ONRAMP_MAX_ORDER_USDT', 50),

    'networks' => [
        'bep20' => [
            'label' => 'BNB Smart Chain (BEP20)',
            'address' => (string) env('ONRAMP_USDT_BEP20_ADDRESS', ''),
            'confirmations' => (int) env('ONRAMP_USDT_BEP20_CONFIRMATIONS', 15),
            'explorer' => env('ONRAMP_USDT_BEP20_EXPLORER', 'https://bscscan.com/tx/'),
        ],
        'polygon' => [
            'label' => 'Polygon PoS',
            'address' => (string) env('ONRAMP_USDT_POLYGON_ADDRESS', ''),
            'confirmations' => (int) env('ONRAMP_USDT_POLYGON_CONFIRMATIONS', 30),
            'explorer' => env('ONRAMP_USDT_POLYGON_EXPLORER', 'https://polygonscan.com/tx/'),
        ],
    ],

    'packages' => [
        'starter' => ['label' => '10 USDT', 'usdt' => 10.0, 'avaxe' => 100.0],
        'plus' => ['label' => '25 USDT', 'usdt' => 25.0, 'avaxe' => 250.0],
        'pro' => ['label' => '50 USDT', 'usdt' => 50.0, 'avaxe' => 500.0],
    ],

    'telegram' => [
        'token' => (string) env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => (string) env('TELEGRAM_WEBHOOK_SECRET', ''),
        'bot_username' => (string) env('TELEGRAM_BOT_USERNAME', ''),
        'admin_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_ADMIN_IDS', ''))))),
    ],
];
