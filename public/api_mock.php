<?php
/**
 * Mock API Simple pour Tests d'Intégration
 * Simule les endpoints API sans Laravel complet
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Récupérer le chemin de la requête
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Router simple
switch ($path) {
    case '/api/referral/leaderboard':
        handleReferralLeaderboard();
        break;
        
    case '/api/whitelist':
        handleWhitelist();
        break;
        
    case '/api/influencer/pools':
        handleInfluencerPools();
        break;
        
    case '/api/influencer/leaderboard':
        handleInfluencerLeaderboard();
        break;
        
    case '/api/escrow/stats':
        handleEscrowStats();
        break;
        
    case '/api/escrow/trades':
        handleEscrowTrades();
        break;
        
    default:
        // Vérifier si c'est une preuve whitelist
        if (preg_match('/\/api\/whitelist\/proof\/(.+)/', $path, $matches)) {
            handleWhitelistProof($matches[1]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
        break;
}

function handleReferralLeaderboard() {
    $leaderboard = [
        [
            'rank' => 1,
            'name' => 'CryptoMaster',
            'wallet_address' => '0x1234...7890',
            'referral_count' => 45,
            'total_rewards' => 4500
        ],
        [
            'rank' => 2,
            'name' => 'BlockchainPro',
            'wallet_address' => '0x9876...4321',
            'referral_count' => 32,
            'total_rewards' => 3200
        ],
        [
            'rank' => 3,
            'name' => 'Web3Guru',
            'wallet_address' => '0x5555...5555',
            'referral_count' => 28,
            'total_rewards' => 2800
        ],
        [
            'rank' => 4,
            'name' => 'DeFiExpert',
            'wallet_address' => '0xaaaa...aaaa',
            'referral_count' => 22,
            'total_rewards' => 2200
        ],
        [
            'rank' => 5,
            'name' => 'NFTCollector',
            'wallet_address' => '0xbbbb...bbbb',
            'referral_count' => 18,
            'total_rewards' => 1800
        ],
        [
            'rank' => 6,
            'name' => 'Test User',
            'wallet_address' => '0x1111...1111',
            'referral_count' => 15,
            'total_rewards' => 1500
        ]
    ];
    
    echo json_encode($leaderboard);
}

function handleWhitelist() {
    $whitelist = [
        'merkle_root' => '0x' . bin2hex(random_bytes(32)),
        'total_addresses' => 156,
        'generated_at' => date('c'),
        'min_balance' => 100
    ];
    
    echo json_encode($whitelist);
}

function handleWhitelistProof($address) {
    // Simuler quelques adresses whitelistées
    $whitelistedAddresses = [
        '0x1111111111111111111111111111111111111111',
        '0x1234567890123456789012345678901234567890',
        '0x9876543210987654321098765432109876543210'
    ];
    
    if (in_array(strtolower($address), array_map('strtolower', $whitelistedAddresses))) {
        echo json_encode([
            'address' => $address,
            'proof' => [
                '0x' . bin2hex(random_bytes(32)),
                '0x' . bin2hex(random_bytes(32)),
                '0x' . bin2hex(random_bytes(32))
            ],
            'merkle_root' => '0x' . bin2hex(random_bytes(32))
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Address not whitelisted']);
    }
}

function handleInfluencerPools() {
    $pools = [
        [
            'id' => 1,
            'name' => 'Influenceurs Français',
            'language' => 'français',
            'milestone' => 5000,
            'pool_milestone' => 30000,
            'reward_amount' => 10,
            'current_referrals' => 24500,
            'progress_percentage' => 81.7,
            'eligible_influencers' => 5,
            'total_influencers' => 8
        ],
        [
            'id' => 2,
            'name' => 'English Influencers',
            'language' => 'english',
            'milestone' => 7500,
            'pool_milestone' => 50000,
            'reward_amount' => 25,
            'current_referrals' => 32100,
            'progress_percentage' => 64.2,
            'eligible_influencers' => 8,
            'total_influencers' => 12
        ],
        [
            'id' => 3,
            'name' => 'Influenciadores Españoles',
            'language' => 'español',
            'milestone' => 4000,
            'pool_milestone' => 20000,
            'reward_amount' => 8,
            'current_referrals' => 15800,
            'progress_percentage' => 79.0,
            'eligible_influencers' => 4,
            'total_influencers' => 6
        ]
    ];
    
    echo json_encode($pools);
}

function handleInfluencerLeaderboard() {
    $leaderboard = [
        [
            'rank' => 1,
            'name' => 'CryptoMaster',
            'pool_name' => 'English Influencers',
            'referral_count' => 8500,
            'total_avax_spent' => 28.5,
            'conversion_rate' => 85.2,
            'has_claimed_reward' => true
        ],
        [
            'rank' => 2,
            'name' => 'Web3Guru',
            'pool_name' => 'Influenceurs Français',
            'referral_count' => 6200,
            'total_avax_spent' => 22.1,
            'conversion_rate' => 78.9,
            'has_claimed_reward' => false
        ],
        [
            'rank' => 3,
            'name' => 'BlockchainPro',
            'pool_name' => 'English Influencers',
            'referral_count' => 5800,
            'total_avax_spent' => 19.7,
            'conversion_rate' => 72.4,
            'has_claimed_reward' => true
        ]
    ];
    
    echo json_encode($leaderboard);
}

function handleEscrowStats() {
    $stats = [
        'total_trades' => 156,
        'active_trades' => 23,
        'total_snt_volume' => 45200,
        'total_avax_volume' => 128.7,
        'average_price' => 0.00285,
        'last_24h_trades' => 12,
        'last_24h_volume_snt' => 8500,
        'last_24h_volume_avax' => 24.3
    ];
    
    echo json_encode($stats);
}

function handleEscrowTrades() {
    $trades = [
        [
            'id' => 1,
            'seller_address' => '0x1234...7890',
            'snt_amount' => 1000,
            'avax_amount' => 3.2,
            'price_per_snt' => 0.0032,
            'status' => 'active',
            'created_at' => date('c', time() - 7200),
            'expires_at' => date('c', time() + 79200),
            'is_own_trade' => false
        ],
        [
            'id' => 2,
            'seller_address' => '0x9876...4321',
            'snt_amount' => 750,
            'avax_amount' => 2.1,
            'price_per_snt' => 0.0028,
            'status' => 'active',
            'created_at' => date('c', time() - 18000),
            'expires_at' => date('c', time() + 68400),
            'is_own_trade' => false
        ],
        [
            'id' => 3,
            'seller_address' => '0x1111...1111',
            'snt_amount' => 2500,
            'avax_amount' => 7.8,
            'price_per_snt' => 0.00312,
            'status' => 'active',
            'created_at' => date('c', time() - 3600),
            'expires_at' => date('c', time() + 82800),
            'is_own_trade' => true
        ]
    ];
    
    $response = [
        'trades' => $trades,
        'pagination' => [
            'current_page' => 1,
            'total_pages' => 1,
            'total_trades' => count($trades)
        ]
    ];
    
    echo json_encode($response);
}
?>

