<?php

// routes/api.php
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\InfluencerController;
use App\Http\Controllers\InternalPayoutController;
use App\Http\Controllers\InternalTradeController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\PoolAutoMatchController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\WalletAuthController;
use App\Http\Controllers\GameMetricsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Broadcast;

// Wallet authentication
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);
Route::post('/auth/challenge', [WalletAuthController::class, 'generateMessage']); // Alias for debug-test.html
Route::post('/auth/verify', [WalletAuthController::class, 'verifySignature']);    // Alias for debug-test.html

// Dev login sans MetaMask (comptes Hardhat déterministes)
Route::get('/wallet/dev-login', [WalletAuthController::class, 'devLogin']);
Route::post('/login', [WalletAuthController::class, 'login']);                    // Direct login for UI/Bots
Route::get('/artefacts', [BlockchainController::class, 'getArtefacts']);           // Publié pour permettre l'init Web3

Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = 'OK';
    } catch (\Exception $e) {
        $db = 'ERROR';
    }

    try {
        \Illuminate\Support\Facades\Cache::put('health', 'ok', 1);
        $cache = 'OK';
    } catch (\Exception $e) {
        $cache = 'ERROR';
    }

    return response()->json([
        'status' => ($db === 'OK' && $cache === 'OK') ? 'OK' : 'ERROR',
        'database' => $db,
        'cache' => $cache,
        'bridge_last_ping' => \Illuminate\Support\Facades\Cache::get('bridge_last_ping', 'NONE')
    ]);
});

// Protected routes (using our custom ApiAuth middleware)
Route::middleware('token.auth')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/metrics/collect', [GameMetricsController::class, 'collect']);
    Route::get('/user/polling-status', [PoolAutoMatchController::class, 'getPollingStatus']);
    Route::post('/user/set-referral', [ReferralController::class, 'applyCodeFromAuthUser']);
    Route::get('/referral/status', [ReferralController::class, 'getStatus']);
    Route::get('/referral/reward-history', [ReferralController::class, 'getRewardHistory']);
    Route::post('/referral/validate', [ReferralController::class, 'validateReferral']);
    Route::post('/marketplace/purchase', [MarketplaceController::class, 'handleTokenPurchase']);
    Route::post('/user/pre-moves', [PoolAutoMatchController::class, 'storePremoves']);
    Route::post('/pre-moves', [PoolAutoMatchController::class, 'storePremoves']);       // Alias for debug-test.html
    Route::get('/user/status', [PoolAutoMatchController::class, 'getPollingStatus']);  // Alias for debug-test.html
    Route::post('/ipfs/upload', [\App\Http\Controllers\IpfsController::class, 'upload']);
    
    // Broadcasting Auth using our custom token authentication (Allow GET and POST to avoid 405)
    Route::match(['get', 'post'], '/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

});

// Public referral leaderboard
Route::get('/referral/leaderboard', [ReferralController::class, 'getLeaderboard']);

Route::post('/debug-referral', [ReferralController::class, 'applyCodeFromAuthUser']);

// SECURITY: /api/debug/trigger-event REMOVED — it was a public backdoor that
// allowed unauthenticated balance modification and arbitrary event emission.

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
// == Routes ADMIN (Protected by IsAdmin Middleware)
// ===============================================
Route::prefix('admin')->middleware(['token.auth', 'is_admin'])->group(function () {
    Route::get('/stats', [\App\Http\Controllers\AdminController::class, 'getStats']);
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'getUsers']);
    Route::post('/users/{id}/status', [\App\Http\Controllers\AdminController::class, 'updateUserStatus']);
    
    Route::get('/applications', [\App\Http\Controllers\AdminController::class, 'getApplications']);
    Route::post('/applications/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveApplication']);
    Route::post('/applications/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectApplication']);
    
    // Settings API
    Route::get('/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index']);
    Route::post('/settings', [\App\Http\Controllers\AdminSettingsController::class, 'update']);

    // --- Admin Routes for Cards ---
    Route::get('/cards', [\App\Http\Controllers\Admin\CardController::class, 'index']);
    Route::post('/cards', [\App\Http\Controllers\Admin\CardController::class, 'store']);
    Route::put('/cards/{id}', [\App\Http\Controllers\Admin\CardController::class, 'update']);
    Route::delete('/cards/{id}', [\App\Http\Controllers\Admin\CardController::class, 'destroy']);
});

// ===============================================
// == Routes Boutique (Cards)
// ===============================================
Route::prefix('shop')->middleware(['token.auth', 'throttle:10,1'])->group(function () {
    Route::get('/cards', [\App\Http\Controllers\ShopController::class, 'index']);
    Route::get('/inventory', [\App\Http\Controllers\ShopController::class, 'inventory']);
    Route::post('/buy', [\App\Http\Controllers\ShopController::class, 'buy']);
    Route::post('/activate-card', [\App\Http\Controllers\ShopController::class, 'activateCard']);
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
    Route::post('/handle-claim', [BlockchainController::class, 'handleClaim']);
    Route::post('/handle-pool-emited', [PoolAutoMatchController::class, 'poolEmitedRequest']);
    Route::post('/update-setting', [BlockchainController::class, 'updateSetting']);
    Route::post('/handle-stagnant-refund', [PoolAutoMatchController::class, 'handleStagnantRefund']);
    Route::post('/batch-processing', [PoolAutoMatchController::class, 'processBatch']);
    Route::post('/batch-processing-all', [PoolAutoMatchController::class, 'processAllBetTiers']);
    Route::post('/internal-pools', [PoolAutoMatchController::class, 'processInternalPools']);
    // SECURITY: /internal/payout REMOVED — it allowed arbitrary payouts to any
    // address using only the internal secret (which is in plaintext in git).
    // Legitimate payouts go through ProcessPayoutJob (dispatched by SessionManager).
    Route::post('/ping', function () {
        \Illuminate\Support\Facades\Cache::put('bridge_last_ping', now()->timestamp, 60);
        return response()->json(['success' => true]);
    });
    //todo: do not forget sendPremove from frontend to backend since we use now we use token auth middleware

});

// ===============================================

// Admin Influencer Management Routes (PROTECTED)
Route::prefix('admin/influencer')->middleware(['token.auth', 'is_admin'])->group(function () {
    Route::post('/create-pool', [InfluencerController::class, 'createPool']);
    Route::post('/add-influencer', [InfluencerController::class, 'addInfluencer']);
    Route::put('/{influencerId}/eligibility', [InfluencerController::class, 'updateEligibility']);
    Route::post('/update-stats', [InfluencerController::class, 'updateStats']);
});

// Whitelist API
Route::get('/whitelist', function () {
    try {
        if (! Storage::disk('public')->exists('whitelist.json')) {
            return response()->json([
                'error' => 'Whitelist not found',
            ], 404);
        }

        $whitelistData = json_decode(
            Storage::disk('public')->get('whitelist.json'),
            true
        );

        return response()->json([
            'addresses' => $whitelistData['addresses'],
            'root' => $whitelistData['root'],
            'count' => count($whitelistData['addresses']),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to load whitelist: '.$e->getMessage(),
        ], 500);
    }
});

Route::get('/whitelist/proof/{address}', function ($address) {
    try {
        if (! Storage::disk('public')->exists('whitelist.json')) {
            return response()->json([
                'error' => 'Whitelist not found',
            ], 404);
        }

        $whitelistData = json_decode(
            Storage::disk('public')->get('whitelist.json'),
            true
        );

        $address = strtolower($address);

        if (! in_array($address, $whitelistData['addresses'])) {
            return response()->json([
                'error' => 'Address not whitelisted',
            ], 404);
        }

        return response()->json([
            'address' => $address,
            'proof' => $whitelistData['proofs'][$address] ?? [],
            'root' => $whitelistData['root'],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to get proof: '.$e->getMessage(),
        ], 500);
    }
});

Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
Route::middleware('api')->group(function () {
    Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
});

// Public referral routes
Route::get('/referrals/leaderboard', [ReferralController::class, 'getLeaderboard']);
