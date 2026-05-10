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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Broadcast;

// Wallet authentication
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);
Route::post('/auth/challenge', [WalletAuthController::class, 'generateMessage']); // Alias for debug-test.html
Route::post('/auth/verify', [WalletAuthController::class, 'verifySignature']);    // Alias for debug-test.html
Route::post('/login', [WalletAuthController::class, 'login']);                    // Direct login for UI/Bots
Route::get('/artefacts', [BlockchainController::class, 'getArtefacts']);           // Publié pour permettre l'init Web3

// Protected routes (using our custom ApiAuth middleware)
Route::middleware('token.auth')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
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

Route::post('/debug/trigger-event', function (Request $request) {
    $type = $request->input('type');
    $wallet = $request->input('wallet', '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266');
    $user = \App\Models\User::where('wallet_address', strtolower($wallet))->first();
    
    if (!$user) return response()->json(['error' => 'User not found for address ' . $wallet], 404);

    switch($type) {
        case 'balance':
            $newBalance = $request->input('value', 9.99);
            $user->update(['balance' => $newBalance]);
            event(new \App\Events\BalanceUpdated($user->id, $newBalance));
            break;
        case 'match':
            // Emit a global arena event that the UI listens to as 'BasicEvent'
            event(new \App\Events\GlobalArenaEvent("Global Match Found for user " . $user->id, 'match'));
            break;
        case 'fight':
            $outcome = $request->input('value', 'win');
            $delta = ($outcome === 'win') ? '+0.01' : ($outcome === 'draw' ? '0' : '-0.01');
            $myMove = $request->input('my_move', 'rock');
            $oppMove = $request->input('opp_move', ($outcome === 'win' ? 'scissors' : ($outcome === 'draw' ? 'rock' : 'paper')));
            $newBalance = $user->balance + (float)$delta;
            $user->update(['balance' => $newBalance]);
            event(new \App\Events\FightResult($user, $outcome, $delta, (string)$newBalance, $myMove, $oppMove));
            break;
        case 'discovery':
            // Simulates a PoolEmitted event
            event(new \App\Events\PoolEmitted("pool_".uniqid(), [$user->wallet_address], 0.01));
            break;
        case 'victory':
            event(new \App\Events\SessionFinished($user, 'SUCCESS', '1.25', true));
            break;
        case 'ruin':
            event(new \App\Events\SessionFinished($user, 'RUIN', '0.00', false));
            break;
        default:
            return response()->json(['error' => 'Invalid event type'], 400);
    }

    return response()->json(['message' => "Event $type triggered successfully"]);
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
    Route::post('/update-setting', [BlockchainController::class, 'updateSetting']);
    Route::post('/handle-stagnant-refund', [PoolAutoMatchController::class, 'handleStagnantRefund']);
    Route::post('/batch-processing', [PoolAutoMatchController::class, 'processBatch']);
    Route::post('/batch-processing-all', [PoolAutoMatchController::class, 'processAllBetTiers']);
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
Route::post('/referrals/validate', [ReferralController::class, 'validateReferral']);
