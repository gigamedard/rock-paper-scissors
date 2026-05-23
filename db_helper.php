<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\UserCard;

if ($argc < 3) {
    echo json_encode(['error' => 'Not enough arguments. Usage: php db_helper.php <action> <args...>']);
    exit(1);
}

$action = $argv[1];

if ($action === 'get_user') {
    $wallet = strtolower($argv[2]);
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $user->load('userCards.card');
    echo json_encode([
        'id' => $user->id,
        'wallet_address' => $user->wallet_address,
        'balance' => $user->balance,
        'battle_balance' => $user->battle_balance,
        'token_balance' => $user->token_balance,
        'status' => $user->status,
        'bet_amount' => $user->bet_amount,
        'session_start_balance' => $user->session_start_balance,
        'session_started' => $user->session_started,
        'active_limits' => $user->active_limits,
        'cards' => $user->userCards->map(function ($uc) {
            return [
                'id' => $uc->id,
                'card_id' => $uc->card_id,
                'name' => $uc->card->name,
                'effect_type' => $uc->card->effect_type,
                'effect_value' => $uc->card->effect_value,
                'duration_type' => $uc->card->duration_type,
                'duration_value' => $uc->card->duration_value,
                'status' => $uc->status,
                'remaining_sessions' => $uc->remaining_sessions,
                'expires_at' => $uc->expires_at ? $uc->expires_at->toIso8601String() : null,
                'tx_hash' => $uc->tx_hash,
            ];
        })
    ], JSON_PRETTY_PRINT);
} elseif ($action === 'set_balance') {
    $wallet = strtolower($argv[2]);
    $balance = (float)$argv[3];
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $user->balance = $balance;
    $user->save();
    echo json_encode(['success' => true, 'balance' => $user->balance]);
} elseif ($action === 'expire_time_cards') {
    $wallet = strtolower($argv[2]);
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $userCards = UserCard::where('user_id', $user->id)
        ->where('status', 'available')
        ->whereHas('card', function ($q) {
            $q->where('duration_type', 'time');
        })
        ->get();
    foreach ($userCards as $uc) {
        $uc->expires_at = now()->subMinutes(10);
        $uc->save();
    }
    // Sync limits to blockchain
    $user->load('userCards.card');
    $user->syncLimitsToBlockchain();
    echo json_encode(['success' => true, 'expired_count' => $userCards->count()]);
} elseif ($action === 'grant_card') {
    // Forcefully grants a card to user (useful for helper bot #7, etc.)
    $wallet = strtolower($argv[2]);
    $cardId = (int)$argv[3];
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $card = \App\Models\Card::findOrFail($cardId);
    $expiresAt = null;
    if ($card->duration_type === 'time') {
        $expiresAt = now()->addHours($card->duration_value);
    }
    $uc = UserCard::create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'status' => 'available',
        'remaining_sessions' => $card->duration_type === 'sessions' ? $card->duration_value : null,
        'expires_at' => $expiresAt,
        'tx_hash' => 'granted_by_admin_' . uniqid(),
    ]);
    // Sync limits to blockchain
    $user->load('userCards.card');
    $user->syncLimitsToBlockchain();
    echo json_encode(['success' => true, 'user_card_id' => $uc->id]);
} elseif ($action === 'set_session_start_balance') {
    $wallet = strtolower($argv[2]);
    $val = (float)$argv[3];
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $user->session_start_balance = $val;
    $user->save();
    echo json_encode(['success' => true, 'session_start_balance' => $user->session_start_balance]);
} elseif ($action === 'force_session_start') {
    // Sets session_started=true + session_start_balance + bet_amount
    // Purpose: prevent PoolReconstructionService from overwriting session_start_balance during pool init
    $wallet = strtolower($argv[2]);
    $sessionStartBalance = (float)$argv[3];
    $betAmount = isset($argv[4]) ? (float)$argv[4] : null;
    $user = User::where('wallet_address', $wallet)->first();
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit(1);
    }
    $user->session_started = true;
    $user->session_start_balance = $sessionStartBalance;
    if ($betAmount !== null) {
        $user->bet_amount = $betAmount;
    }
    $user->save();
    echo json_encode(['success' => true, 'session_started' => true, 'session_start_balance' => $user->session_start_balance, 'bet_amount' => $user->bet_amount]);
} elseif ($action === 'set_setting') {
    $key = $argv[2];
    $val = $argv[3];
    $setting = \App\Models\GameSetting::setValue($key, $val);
    echo json_encode(['success' => true, 'key' => $key, 'value' => $val]);
} else {
    echo json_encode(['error' => 'Unknown action: ' . $action]);
    exit(1);
}
