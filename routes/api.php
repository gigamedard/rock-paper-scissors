<?php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\PoolAutoMatchController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\InfluencerController;
use App\Http\Controllers\EscrowController;
use App\Http\Controllers\WalletAuthController;
use Illuminate\Support\Facades\Storage;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/play', [GameController::class, 'play']);
    
    // Referral routes
    Route::get('/referral/status', [ReferralController::class, 'getStatus']);
    Route::post('/referral/process', [ReferralController::class, 'processReferral']);
    Route::post('/referral/validate', [ReferralController::class, 'validateReferral']);
    
    // Influencer routes
    Route::get('/influencer/stats', [InfluencerController::class, 'getStats']);
    Route::post('/influencer/claim-reward', [InfluencerController::class, 'claimReward']);
    
    // Escrow routes
    Route::post('/escrow/create-trade', [EscrowController::class, 'createTrade']);
    Route::post('/escrow/accept-trade/{tradeId}', [EscrowController::class, 'acceptTrade']);
    Route::post('/escrow/cancel-trade/{tradeId}', [EscrowController::class, 'cancelTrade']);
});

// Public routes
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);
Route::get('/influencer/pools', [InfluencerController::class, 'getPools']);
Route::get('/escrow/trades', [EscrowController::class, 'getTrades']);
Route::get('/escrow/trade/{tradeId}', [EscrowController::class, 'getTrade']);
Route::get('/escrow/stats', [EscrowController::class, 'getStats']);

// Admin routes (should have admin middleware in production)
Route::post('/admin/influencer/create-pool', [InfluencerController::class, 'createPool']);
Route::post('/admin/influencer/add-influencer', [InfluencerController::class, 'addInfluencer']);
Route::put('/admin/influencer/{influencerId}/eligibility', [InfluencerController::class, 'updateEligibility']);
Route::post('/admin/influencer/update-stats', [InfluencerController::class, 'updateStats']);

// Whitelist API
Route::get('/whitelist', function () {
    try {
        if (!Storage::disk('public')->exists('whitelist.json')) {
            return response()->json([
                'error' => 'Whitelist not found'
            ], 404);
        }

        $whitelistData = json_decode(
            Storage::disk('public')->get('whitelist.json'), 
            true
        );

        return response()->json([
            'addresses' => $whitelistData['addresses'],
            'root' => $whitelistData['root'],
            'count' => count($whitelistData['addresses'])
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to load whitelist: ' . $e->getMessage()
        ], 500);
    }
});

Route::get('/whitelist/proof/{address}', function ($address) {
    try {
        if (!Storage::disk('public')->exists('whitelist.json')) {
            return response()->json([
                'error' => 'Whitelist not found'
            ], 404);
        }

        $whitelistData = json_decode(
            Storage::disk('public')->get('whitelist.json'), 
            true
        );

        $address = strtolower($address);
        
        if (!in_array($address, $whitelistData['addresses'])) {
            return response()->json([
                'error' => 'Address not whitelisted'
            ], 404);
        }

        return response()->json([
            'address' => $address,
            'proof' => $whitelistData['proofs'][$address] ?? [],
            'root' => $whitelistData['root']
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to get proof: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
Route::middleware('api')->group(function () {
    Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
});

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// Routes pour l'authentification par portefeuille (Wallet Authentication)
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);


// Routes protégées (nécessitent un jeton d'authentification)
Route::middleware('auth:sanctum')->group(function () {
    
    // A route to get the authenticated user's details
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // The route to start the game
    Route::post('/game/start', [GameController::class, 'start']);

    // The route to apply a referral code
    Route::post('/user/set-referral', [ReferralController::class, 'applyCodeFromAuthUser']);

});


// Referral (protected by token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/referral-status', [ReferralController::class, 'getStatus']);
    Route::post('/user/set-referral', [ReferralController::class, 'applyCodeFromAuthUser']);
});

// Public referral routes
Route::get('/referrals/leaderboard', [ReferralController::class, 'getLeaderboard']);
Route::post('/referrals/validate', [ReferralController::class, 'validateReferral']);
