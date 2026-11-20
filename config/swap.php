<?php

return [
    'enabled' => (bool) env('SWAP_ENABLED', true),
    'rate' => (float) env('SWAP_RATE', 7.13),
    'fee_percent' => (float) env('SWAP_FEE_PERCENT', 0),
    'min_usdt' => (float) env('SWAP_MIN_USDT', 0.5),
    'max_usdt' => (float) env('SWAP_MAX_USDT', 10),
    'min_avaxe' => (float) env('SWAP_MIN_AVAXE', 0.1),
    'max_avaxe' => (float) env('SWAP_MAX_AVAXE', 1.4025),
    'avaxe_contract' => (string) env('AVAXE_ADDRESS', '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7'),
    'avaxe_decimals' => 18,
    'pingala_rpc' => (string) env('PINGALA_RPC_URL', ''),
    'usdt_treasury_address' => (string) env('SWAP_USDT_TREASURY_ADDRESS', '0xE4b74c895eA6B5c599AE578859Cce74E9317a40B'),
    'avaxe_hot_address' => (string) env('SWAP_AVAXE_HOT_ADDRESS', ''),
    'networks' => [
        'polygon' => [
            'label' => 'Polygon',
            'usdt' => (string) env('ONRAMP_POLYGON_USDT_CONTRACT', '0xc2132D05D31c914a87C6611C10748AEb04B58e8F'),
            'chain_id' => 137,
            'usdt_decimals' => 6,
            'rpc' => (string) env('ONRAMP_POLYGON_RPC_URL', 'https://polygon-bor-rpc.publicnode.com'),
            'confirmations' => (int) env('SWAP_POLYGON_CONFIRMATIONS', 12),
            'native' => 'POL',
        ],
        'bsc' => [
            'label' => 'BNB Smart Chain',
            'usdt' => (string) env('ONRAMP_BEP20_USDT_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),
            'chain_id' => 56,
            'usdt_decimals' => 18,
            'rpc' => (string) env('ONRAMP_BEP20_RPC_URL', 'https://bsc-rpc.publicnode.com'),
            'confirmations' => (int) env('SWAP_BSC_CONFIRMATIONS', 8),
            'native' => 'BNB',
        ],
    ],
];
