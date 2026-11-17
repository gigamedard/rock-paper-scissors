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
    // Prix de référence AVAX/USDT. 1 AVAXe = 1 AVAX (peg interne) ; ce taux
    // convertit le package AVAXe en montant USDT à envoyer. À mettre à jour
    // manuellement (ou via un feed) avant chaque campagne.
    'avax_usdt_rate' => (float) env('ONRAMP_AVAX_USDT_RATE', 7.13),
    'secret_editor_url' => (string) env('ONRAMP_SECRET_EDITOR_URL', ''),
    'treasury_key_path' => (string) env('ONRAMP_TREASURY_KEY_PATH', '/opt/battlepool/.secrets/onramp_treasury_pk'),

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

    // Packages définis en AVAXe (pas en USDT) : le montant USDT est calculé
    // avec `avax_usdt_rate`. `mini` couvre le dépôt minimal d'une session :
    // C=0.04 + R=0.2496 + frais 4% = 0.301184 AVAXe (0.35 pour la marge).
    'packages' => [
        'mini' => ['label' => '0.35 AVAXe — session minimum', 'avaxe' => 0.35],
        'starter' => ['label' => '1 AVAXe', 'avaxe' => 1.0],
        'plus' => ['label' => '5 AVAXe', 'avaxe' => 5.0],
    ],

    'telegram' => [
        'token' => (string) env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => (string) env('TELEGRAM_WEBHOOK_SECRET', ''),
        'bot_username' => (string) env('TELEGRAM_BOT_USERNAME', ''),
        'admin_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_ADMIN_IDS', ''))))),
    ],
];
