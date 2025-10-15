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
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\InternalTradeController;


// Wallet authentication
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);

// Protected routes (using our custom ApiAuth middleware)
Route::middleware('token.auth')->group(function () {
    Route::post('/user/set-referral', [ReferralController::class, 'applyCodeFromAuthUser']);
    Route::get('/referral/status', [ReferralController::class, 'getStatus']);
    Route::get('/referral/reward-history', [ReferralController::class, 'getRewardHistory']);
    Route::post('/referral/validate', [ReferralController::class, 'validateReferral']);
    Route::post('/marketplace/purchase', [MarketplaceController::class, 'handleTokenPurchase']);
    
});

// Public referral leaderboard
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);



Route::post('/debug-referral', [ReferralController::class, 'applyCodeFromAuthUser']);

// Public routes
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);
Route::get('/influencer/pools', [InfluencerController::class, 'getPools']);
Route::get('/escrow/trades', [EscrowController::class, 'getTrades']);
Route::get('/escrow/trade/{tradeId}', [EscrowController::class, 'getTrade']);
Route::get('/escrow/stats', [EscrowController::class, 'getStats']);



// ===============================================
// == Routes pour le Marketplace
// ===============================================
Route::prefix('marketplace')->middleware('token.auth')->group(function () {
    // --- Routes de Lecture ---
    Route::get('/stats', [MarketplaceController::class, 'getStats']);
    Route::get('/trades', [MarketplaceController::class, 'getActiveTrades']);

    // --- Routes d'Écriture (que nous implémenterons plus tard) ---
    Route::post('/create-offer', [MarketplaceController::class, 'createOffer']);
    // Route::post('/fulfill-offer', [MarketplaceController::class, 'fulfillOffer']);
    // Route::post('/cancel-offer', [MarketplaceController::class, 'cancelOffer']);
});

// ===============================================
// == Routes Internes (pour le listener blockchain)
// ===============================================
Route::prefix('internal/trades')->middleware('auth.internal')->group(function () { // Note: On utilisera un middleware de sécurité plus tard
    Route::post('/create', [InternalTradeController::class, 'create']);
    Route::post('/update-status', [InternalTradeController::class, 'updateStatus']); // <-- AJOUTE OU DÉCOMMENTE CETTE LIGNE
});














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




    











// Public referral routes
Route::get('/referrals/leaderboard', [ReferralController::class, 'getLeaderboard']);
Route::post('/referrals/validate', [ReferralController::class, 'validateReferral']);
