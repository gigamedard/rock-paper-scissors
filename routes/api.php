<?php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\PoolAutoMatchController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\InfluencerController;
use App\Http\Controllers\InternalPayoutController;
use App\Http\Controllers\WalletAuthController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\InternalTradeController;


// Wallet authentication
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);

// Protected routes (using our custom ApiAuth middleware)
Route::middleware('token.auth')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/user/set-referral', [ReferralController::class, 'applyCodeFromAuthUser']);
    Route::get('/referral/status', [ReferralController::class, 'getStatus']);
    Route::get('/referral/reward-history', [ReferralController::class, 'getRewardHistory']);
    Route::post('/referral/validate', [ReferralController::class, 'validateReferral']);
    Route::post('/marketplace/purchase', [MarketplaceController::class, 'handleTokenPurchase']);
    Route::post('/user/pre-moves', [PoolAutoMatchController::class, 'storePremoves']);
    Route::get('/artefacts', [BlockchainController::class, 'getArtefacts']);
    Route::post('/ipfs/upload', [\App\Http\Controllers\IpfsController::class, 'upload']);

});

// Public referral leaderboard
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);



Route::post('/debug-referral', [ReferralController::class, 'applyCodeFromAuthUser']);

Route::post('/debug-session-finish', function (Request $request) {
    $userId = $request->input('user_id');
    $user = \App\Models\User::find($userId);
    if (!$user) return response()->json(['error' => 'User not found'], 404);
    
    // Mock a pool for the event (optional, listener handles null pool gracefully-ish, but better to have one)
    $pool = \App\Models\Pool::first(); 
    
    event(new \App\Events\SessionFinishedEvent($userId, $pool));
    
    return response()->json(['message' => 'SessionFinishedEvent fired']);
});

// Public routes
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);
Route::get('/influencer/pools', [InfluencerController::class, 'getPools']);
// Route::get('/escrow/trades', [EscrowController::class, 'getTrades']);
// Route::get('/escrow/trade/{tradeId}', [EscrowController::class, 'getTrade']);
// Route::get('/escrow/stats', [EscrowController::class, 'getStats']);



// ===============================================
// == Routes pour le Marketplace
// ===============================================
Route::prefix('marketplace')->middleware('token.auth')->group(function () {
    // --- Routes de Lecture ---
    Route::get('/stats', [MarketplaceController::class, 'getStats']);
    Route::get('/trades', [MarketplaceController::class, 'getActiveTrades']);

    // --- Routes d'Écriture (que nous implémenterons plus tard) ---
    Route::post('/create-offer', [MarketplaceController::class, 'createOffer']);
    Route::post('/fulfill-offer', [MarketplaceController::class, 'fulfillOffer']);
    Route::post('/cancel-offer', [MarketplaceController::class, 'cancelOffer']);
});


// ===============================================
// ==        Routes pour les INFLUENCEURS       ==
// ===============================================
Route::prefix('influencer')->middleware('token.auth')->group(function () {
    
    // NOUVELLE ROUTE PRINCIPALE POUR LE DASHBOARD
    Route::get('/dashboard', [InfluencerController::class, 'getDashboardData']);
    
    // ANCIENNE ROUTE (tu peux la garder ou la supprimer)
    Route::get('/stats', [InfluencerController::class, 'getStats']);

    // ANCIENNE ROUTE (tu peux la garder ou la supprimer)
    Route::get('/pools', [InfluencerController::class, 'getPools']);

    // Route pour réclamer la récompense
    Route::post('/claim-reward', [InfluencerController::class, 'claimReward']);
    
    // Route de TEST pour devenir influenceur
    Route::post('/join-test', [InfluencerController::class, 'joinTestProgram']);

    // --- Gestion des Candidatures ---
    Route::post('/apply', [InfluencerController::class, 'apply']);
    Route::get('/application-status', [InfluencerController::class, 'getApplicationStatus']);
});

// ===============================================
// == Routes ADMIN (Protected by checkAdmin)    ==
// ===============================================
Route::prefix('admin')->middleware('token.auth')->group(function () {
    Route::get('/applications', [\App\Http\Controllers\AdminController::class, 'getApplications']);
    Route::post('/applications/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveApplication']);
    Route::post('/applications/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectApplication']);
});


// ===============================================
// == Routes Internes (pour le serveur/listener Node.js)
// ===============================================
Route::prefix('internal')->middleware('auth.internal')->group(function () {
    
    // --- Routes du Marketplace ---
    Route::post('/trades/create', [InternalTradeController::class, 'create']);
    Route::post('/trades/update-status', [InternalTradeController::class, 'updateStatus']);
    Route::post('/trades/trigger-referral-check', [InternalTradeController::class, 'triggerReferralCheck']);
    Route::post('/trades/sync-transfer', [InternalTradeController::class, 'syncTransfer']);
    
    // --- Routes des Influenceurs ---
    Route::post('/influencer/log-fee', [InfluencerController::class, 'logFee']);

    // --- Routes du JEU (les nouvelles que tu migres) ---

    
    Route::post('/update-balance', [BlockchainController::class, 'updateUserBalance']); 
    Route::post('/handle-pool-emited', [PoolAutoMatchController::class, 'poolEmitedRequest']);
    Route::post('/handle-stagnant-refund', [PoolAutoMatchController::class, 'handleStagnantRefund']);
    Route::post('/batch-processing', [PoolAutoMatchController::class, 'processBatch']);
    Route::post('/internal-pools', [PoolAutoMatchController::class, 'processInternalPools']);
    Route::post('/payout', [InternalPayoutController::class, 'payout']);
    //todo: do not forget sendPremove from frontend to backend since we use now we use token auth middleware
    
});

// ===============================================

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
