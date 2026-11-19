<?php

return [
    'enabled' => (bool) env('OFFRAMP_ENABLED', true),
    'terms_version' => env('OFFRAMP_TERMS_VERSION', 'v1'),
    'terms_url' => (string) env('ONRAMP_TERMS_URL', ''),
    'require_kyc' => (bool) env('OFFRAMP_REQUIRE_KYC', true),
    'rate' => (float) env('OFFRAMP_AVAX_USDT_RATE', 7.13),
    'fee_percent' => (float) env('OFFRAMP_FEE_PERCENT', 0),
    'min_avaxe' => (float) env('OFFRAMP_MIN_AVAXE', 0.1),
    'max_avaxe' => (float) env('OFFRAMP_MAX_AVAXE', 10),
    'daily_limit_avaxe' => (float) env('OFFRAMP_DAILY_LIMIT_AVAXE', 50),
    'auto_max_usdt' => (float) env('OFFRAMP_AUTO_MAX_USDT', 50),
    'payout_network' => (string) env('OFFRAMP_PAYOUT_NETWORK', 'polygon'),
    'confirmations' => (int) env('OFFRAMP_PINGALA_CONFIRMATIONS', 2),
    'min_usdt' => (float) env('OFFRAMP_MIN_USDT', 0.1),
];
