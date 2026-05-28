<?php
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FightController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\AutoMatchController;
use App\Http\Controllers\WalletAuthController;
use App\Http\Controllers\BlockchainController;


use App\Http\Controllers\LanguageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PoolAutoMatchController;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Auth\EnhancedRegistrationController;

use Illuminate\Support\Str;
use kornrunner\Keccak;

use App\Models\Challenge;
use App\Models\Pool;
use App\Models\User;
use App\Models\PreMove;
use App\Models\Fight;   

use App\Events\testevent;
use App\Helpers\Web3Helper;

use App\Events\BalanceUpdated;
use App\Events\BasicEvent;


use App\Models\InfluencerFee;

// Language selection
Route::get('/select-language', [LanguageController::class, 'index'])->name('language.select');
Route::post('/set-language/{locale}', [LanguageController::class, 'setLanguage'])->name('language.set');



Route::get('/', function () {
    return file_get_contents(public_path('index.html'));
});

// Route "catch-all" pour que le rafraîchissement (F5) fonctionne sur des sous-pages


Route::get('/handle-pool-emited', [PoolAutoMatchController::class, 'poolEmitedRequest']);
Route::get('/update-balance', [BlockchainController::class, 'updateUserBalance']);


/*

/*
Route::get('/', function () {
    // If no language set, redirect to selection page
    if (!session()->has('locale')) {
        return redirect()->route('language.select');
    }

    // Otherwise load the main game view
    return view('welcome'); // or your game start view
});*/


Route::get('/counter', function (Request $request) {
    /*Validate the incoming request
    $validated = $request->validate([
        'action' => 'required|string',
        'counter' => 'required|numeric',
    ]);

    // Log the action
    Log::info("Counter Updated via API: Action: {$validated['action']}, Counter: {$validated['counter']}");

    DB::table('counters')->updateOrInsert(
        ['id' => 1], // Replace '1' with a unique identifier, if needed
        ['value' => $validated['counter']]
    );

    // Return a JSON response
    return response()->json(['message' => 'Counter updated successfully'], 200);*/
/*
    $users = [
        "0xF3A5D3E6A8CFA57Fdb18aAf4aEaf5Dd8A40BF02E",
        "0x1B7d8dF3cF9Ae5A9E8e40f3cB4D3E3eB2aA7e10F",
        "0x9c7A1dBf3F2dE4A7C5b6F1D3A2B9C4E6F8A1D2C3",
        "0x3C9a5aD8E7fA2B4c9e3F8B2D7aF1E6C3b4A5d8f7",
        "0xE6B4d7F3a2C1B9e5F8A4d7C2b3E9F1A5C6D8e7F0"
    ];

    $usersCollection = User::whereIn('wallet_address', $users)->get();

Log::info('===================================>usersCollection: ' . json_encode($usersCollection));

Log::info('Type of addresses: ' . gettype($addresses));
Log::info('Type of users: ' . gettype($users));
dd($usersCollection);
});
Route::get('/test-event', function () {
    event(new testevent(1, 123));
    return response()->json(['message' => 'Event fired successfully']);
});



Route::get('/test-basic-event', function () {
    event(new BasicEvent('Hello from BasicEvent!'));
    return 'BasicEvent Dispatched!';
});



Route::get('/user_create', function () {
    $user1 = User::factory()->create(['wallet_address' => '0xF3A5D3E6A8CFA57Fdb18aAf4aEaf5Dd8A40BF02E']);
    $user2 = User::factory()->create(['wallet_address' => '0x1B7d8dF3cF9Ae5A9E8e40f3cB4D3E3eB2aA7e10F']);
    $user3 = User::factory()->create(['wallet_address' => '0x9c7A1dBf3F2dE4A7C5b6F1D3A2B9C4E6F8A1D2C3']);
    $user4 = User::factory()->create(['wallet_address' => '0x3C9a5aD8E7fA2B4c9e3F8B2D7aF1E6C3b4A5d8f7']);
    $user5 = User::factory()->create(['wallet_address' => '0xE6B4d7F3a2C1B9e5F8A4d7C2b3E9F1A5C6D8e7F0']);

    // Simulate a CID mismatch by setting a different CID in the user's pre-move
    $user1->preMove()->create([
        'moves' => json_encode(['rock', 'paper', 'scissors']),
        'cid' => 'QmTestCID1', // Simulate CID mismatch
    ]);

    $user2->preMove()->create([
        'moves' => json_encode(['paper', 'paper', 'scissors']),
        'cid' => 'QmTestCID2', // Simulate CID mismatch
    ]);

    $user3->preMove()->create([
        'moves' => json_encode(['rock', 'rock', 'scissors']),
        'cid' => 'QmTestCID3', // Simulate CID mismatch
    ]);

    $user4->preMove()->create([
        'moves' => json_encode(['rock', 'paper', 'rock']),
        'cid' => 'QmTestCID4', // Simulate CID mismatch
    ]);

    $user5->preMove()->create([
        'moves' => json_encode(['rock', 'rock', 'rock']),
        'cid' => 'QmTestCID5', // Simulate CID mismatch
    ]);
});


Route::get('/web3test', function () {
    // Define wallets and amounts for testing
    $wallets = [
        "0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc",
        "0x976EA74026E726554dB657fA54763abd0C3a0aa9",
        "0x14dC79964da2C08b23698B3D3cc7Ca32193d9955",
        "0x23618e81E3f5cdF7f54C3d65f7FBc0aBf5B21E8f",
    ];

    $amounts = [
        "9888000000000000000", // 9.8 ETH
        "5000000000000000000",  // 5 ETH
        "1000000000000000000",  // 1 ETH
        "2000000000000000000"  // 2 ETH
    ];

    // Call the batch payment route on the Node.js server
    $response = Http::post("http://127.0.0.1:3000/sendBatchPayment", [
        'wallets' => $wallets,
        'amounts' => $amounts,
    ]);

    return $response->json();
});



Route::get('/get_indexes', function () {

    /*$challenge = new Challenge();
    $challenge->sender_id = Auth::id();
    $challenge->receiver_id = 2;
    $challenge->status = 'pending';
    $challenge->save();
    event(new BalanceUpdated(1, 8));
    event(new testevent(1,8.8));
    return view('welcome');
    */

    $f = Fight::find(1);
    $preoveIndex1 = $f->user1->preMove->current_index;
    $preoveIndex2 = $f->user2->preMove->current_index;
    return response()->json([
        'preoveIndex1' => $preoveIndex1,
        'preoveIndex2' => $preoveIndex2,
    ]);
    
});

Route::get('/salt',function(){
            /*// Retrieve the pool with its available users
            $pool = Pool::find(1);

            //dd($pool);

            $availableUsers = $pool->users;

            Log::info('===================================>availableUsers befor sorting: ' . json_encode($availableUsers));

            // Get available users from the pool
           
            
            Log::info('===================================>availableUsers befor sorting: ' . json_encode($availableUsers));
            // sort the users by their wallet address hashed with salt

            $sortedUsers = Web3Helper::sortAddressesWithSalt($availableUsers->pluck('wallet_address')->toArray(), $pool->salt);
            $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedUsers) {
                return array_search($user->wallet_address, $sortedUsers);
            })->values();


            Log::info('===================================>availableUsers after sorting: ' . json_encode($availableUsers));*/
            $addr =  [
                "0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc",
                "0x976EA74026E726554dB657fA54763abd0C3a0aa9",
                "0x14dC79964da2C08b23698B3D3cc7Ca32193d9955"
            ];

            $salt = Web3Helper::generateHash($addr);
            Log::info('===================================>salt: ' . $salt);
            return response()->json(['message' => 'Salt generated successfully'], 200);

});

/*

Route::get('/test-pinata-upload', function () {
    $data = [
        'pool_id' => 1,
        'user1_id' => 1,
        'user1_address' => '0xF3A5D3E6A8CFA57Fdb18aAf4aEaf5Dd8A40BF02E',
        'user1_balance' => 100,
        'old_user1_balance' => 100,
        'user1_battle_balance' => 0,
        'user1_premove_index' => 0,
        'user1_move' => 'rock',
        'user1_gain' => 0,
        'user2_id' => 2,
        'user2_address' => '0x1B7d8dF3cF9Ae5A9E8e40f3cB4D3E3eB2aA7e10F',
        'user2_balance' => 100,
        'old_user2_balance' => 100,
        'user2_battle_balance' => 0,
        'user2_premove_index' => 0,
        'user2_move' => 'rock',
        'user2_gain' => 0,
    ];

    $cid = Web3Helper::sendArchiveToPinata($data);
    return response()->json(['cid' => $cid]);
   
});













Route::get('/get_server_time', function () {
    $timestamp = now()->timestamp; // Get the current timestamp
    return response()->json(['timestamp' => $timestamp]);
});

Route::get('/csrf-token', function () {
    Log::info('started csrf-token');
    return response()->json(['csrfToken' => csrf_token()]);
});






// Dashboard route (Redirect to SPA)
Route::get('/dashboard', function () {
    return file_get_contents(public_path('index.html'));
})->name('dashboard');

// New module routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/referral', function () {
        return inertia('Referral');
    })->name('referral');
    
    Route::get('/influencer', function () {
        return inertia('Influencer');
    })->name('influencer');
    
    Route::get('/marketplace', function () {
        $userAddress = auth()->user()->wallet_address ?? null;
        return inertia('Marketplace', ['userAddress' => $userAddress]);
    })->name('marketplace');
});

// Dynamic module routes (HTML with API integration)
Route::get('/referral-dynamic', function () {
    return view('referral-dynamic');
})->name('referral.dynamic');

Route::get('/influencer-dynamic', function () {
    return view('influencer-dynamic');
})->name('influencer.dynamic');

Route::get('/marketplace-dynamic', function () {
    return view('marketplace-dynamic');
})->name('marketplace.dynamic');

// Challenge routes
Route::middleware(['auth'])->group(function () {
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');
    Route::post('/challenge/send/{userId}/{baseBetAmount}', [ChallengeController::class, 'sendChallenge'])->name('challenge.send');
    Route::post('/challenge/accept/{invitationId}', [ChallengeController::class, 'acceptChallenge'])->name('challenge.accept');
});

Route::get('/challenges/cleanup', [ChallengeController::class, 'deleteOldChallenges']);


// Fight routes
Route::middleware(['auth'])->group(function () {
    Route::post('/fight/{fightId}/{selectedMove}', [FightController::class, 'played'])->name('Fight.played');
    
});

// Users Settings routes
Route::middleware('auth')->group(function() {
    Route::get('/user/settings', [UserSettingsController::class, 'getUserSettings']);
    Route::post('/user/settings', [UserSettingsController::class, 'saveUserSettings']);
});

Route::get('/user/balance', function () {
    return response()->json([
        'balance' => auth()->user()->balance,
    ]);
});
*/



// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
// autoprocess routes

Route::get('/triggermatching', [AutoMatchController::class, 'selectSliceInstenceForAllBetAmount']);// later add a unique use token in parameter for security
Route::post('/user/pre-moves', [AutoMatchController::class, 'storePreMoves']);

Route::get('/batch_pool_processing', [PoolAutoMatchController::class, 'processBatch']);
*/
Route::get('/autoplay', [AutoMatchController::class, 'index']);
/*Route::middleware(['auth'])->post('/user/pre-moves', [AutoMatchController::class, 'storePreMoves']);


Route::get('/test-handle-pool-event', [PoolAutoMatchController::class, 'testHandlePoolEmitedEvent']);

// wallet routes
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
//Route::post('/verify-signature', [WalletAuthController::class, 'verifySignature'])->middleware('throttle:10,1'); // 10 requests per minute
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);
Route::post('/wallet/logout', [WalletAuthController::class, 'logout']);
Route::get('/wallet/testrecovery', [WalletAuthController::class, 'testRecovery']);
Route::get('/update-counter', [BlockchainController::class, 'updateCounter']);
*/
Route::get('/artefacts', [BlockchainController::class, 'getArtefacts']);
Route::middleware('auth:sanctum')->get('/notifications/poll', [\App\Http\Controllers\NotificationController::class, 'poll']);

// get route for simulate-user
Route::get('/simulate-user', [PoolAutoMatchController::class, 'simulateUser']);



/*Route::get('/handle-pool-emited', function (Request $request) {
    Log::info('===================================>befor validation ');

    // Validate all fields as strings
    $validated = $request->validate([
        'pool_id' => 'required|string',
        'base_bet' => 'required|string', // Validate as string first
        'users' => 'required|string',    // Validate as string first
        'premove_cids' => 'required|string', // Validate as string first
        'pool_salt' => 'required|string',
    ]);

    Log::info('===================================>after validation ');

    // Manually cast values to their expected types
    $poolId = $validated['pool_id']; // Already a string
    $baseBet = (float) $validated['base_bet']; // Cast to float/numeric

    // Convert the users string into an array
    $users = explode(',', $validated['users']); // Split the string by commas

    // Convert the premove_cids string into an array
    $premoveCIDs = explode(',', $validated['premove_cids']); // Split the string by commas

    $poolSalt = $validated['pool_salt']; // Already a string

    // Ensure the arrays have at least 2 elements
    if (count($users) < 2 || count($premoveCIDs) < 2) {
        return response()->json(['error' => 'Arrays must have at least 2 elements'], 422);
    }

    try {
        $this->handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt);
        Log::info('===================================>$this->handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt);');
        return response()->json(['message' => 'Pool emitted Request handled successfully'], 200);
    } catch (\Exception $e) {
        Log::error('error in poolEmitedRequest: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

*/

    

Route::get('/test-ipfs-direct', function () {
    $data = ['message' => 'Hello from Direct IPFS!', 'timestamp' => time()];
    $ipfsService = app(\App\Services\IpfsService::class);
    
    // Test Upload
    $cid = $ipfsService->uploadJson($data);
    
    if (!$cid) {
        return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
    }
    
    // Test Retrieve
    $retrievedData = $ipfsService->retrieveJson($cid);
    
    return response()->json([
        'success' => true,
        'cid' => $cid,
        'original_data' => $data,
        'retrieved_data' => $retrievedData,
        'match' => ($data == $retrievedData)
    ]);
});

/*


Route::get('/debug', function () {
    Log::info('Backtrace', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10));

    return response()->json(['status' => 'deleted']);
});


// ==========================================================
// == ROUTE DE TEST POUR LE DASHBOARD INFLUENCEUR
use App\Models\Referral;
use App\Models\Influencer;
use App\Models\InfluencerPool;
use App\Models\InfluencerStat;



Route::get('/setup-test-data', function () {

    // --- ADRESSE DE TEST POUR NOTRE INFLUENCEUR ---
    // C'est le compte que tu devras importer dans MetaMask
    $influencerWallet = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';

    // On s'assure que les anciens tests sont supprimés
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    Influencer::truncate();
    InfluencerPool::truncate();
    InfluencerStat::truncate();
    InfluencerFee::truncate();
    Referral::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    // On supprime et recrée proprement l'utilisateur influenceur et ses filleuls
    User::where('wallet_address', $influencerWallet)->delete();
    User::where('email', 'like', 'filleul%@test.com')->delete();

    echo "<h1>Nettoyage... OK</h1>";

    // --- ÉTAPE 1 : CRÉER LE POOL (GROUPE DE LANGUE) ---
    $poolFr = InfluencerPool::firstOrCreate(
        ['language' => 'fr'],
        [
            'name' => 'Pool Francophone',
            'milestone' => 10,             // Jalon personnel
            'pool_milestone' => 50,      // Jalon de groupe
            'reward_amount' => 10,       // Récompense totale de 10 AVAX
            'is_active' => true
        ]
    );
    echo "<h2>1. Pool '{$poolFr->name}' créé.</h2>";

    // --- ÉTAPE 2 : CRÉER L'INFLUENCEUR ---
    $influencerUser = User::create([
        'wallet_address' => $influencerWallet,
        'name' => 'Hardhat Influencer #1',
        'email' => 'influencer@hardhat.test', // Email factice, non utilisé pour le login
        'password' => bcrypt('password'), // Mot de passe factice
        'language' => 'fr',
        'token_balance' => 100,
        'is_validated' => true, // On le valide pour qu'il puisse générer des frais
    ]);

    $influencer = Influencer::create([
        'user_id' => $influencerUser->id,
        'pool_id' => $poolFr->id,
        'is_eligible' => true,
        'has_claimed' => false
    ]);

    $influencerStats = InfluencerStat::create([
        'influencer_id' => $influencer->id,
        'referral_count' => 0,
        'total_avax_spent' => 0
    ]);
    echo "<h2>2. Influenceur '{$influencerUser->name}' ({$influencerWallet}) créé.</h2>";


    // --- ÉTAPE 3 : CRÉER DES FILLEULS POUR CET INFLUENCEUR ---
    $filleulsValides = 0;
    for ($i = 1; $i <= 15; $i++) {
        $filleul = User::create([
            'name' => "Filleul Test #$i",
            'email' => "filleul$i@test.com",
            'password' => bcrypt('password'),
            'wallet_address' => "0xTESTFILLEUL" . str_pad($i, 32, '0', STR_PAD_LEFT), // Adresse factice
            'language' => 'fr',
            'token_balance' => 0,
        ]);

        $status = ($i <= 7) ? 'validated' : 'pending'; // 7 filleuls sont validés
        if ($status === 'validated') {
            $filleulsValides++;
            $filleul->update(['is_validated' => true]);
        }

        Referral::create([
            'referrer_id' => $influencerUser->id,
            'referred_id' => $filleul->id,
            'status' => $status,
        ]);
    }
    $influencerStats->update(['referral_count' => $filleulsValides]);
    echo "<h2>3. Créé 15 filleuls pour l'influenceur ($filleulsValides validés).</h2>";

    
    // --- ÉTAPE 4 : SIMULER DES FRAIS GÉNÉRÉS PAR LE GROUPE ---
    $filleuls = $influencerUser->referrals()->where('status', 'validated')->get()->pluck('referred');
    $totalFees = 0;
    foreach ($filleuls as $filleul) {
        $fee = 0.05;
        InfluencerFee::create([
            'user_id' => $filleul->id,
            'language_code' => $filleul->language,
            'fee_amount_avax' => $fee,
        ]);
        $totalFees += $fee;
    }
    echo "<h2>4. Simulé {$totalFees} AVAX de frais générés.</h2>";

    return "<h1>TERMINÉ !</h1><p>Données de test créées pour le portefeuille {$influencerWallet}.</p>";
});
*/


// Auth routes
require __DIR__.'/auth.php';

// Token to Session Bridge for Admin
Route::get('/admin/login-via-token', [\App\Http\Controllers\WalletAuthController::class, 'loginByToken']);

// Admin Settings Routes
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings', [\App\Http\Controllers\AdminSettingsController::class, 'update'])->name('admin.settings.update');
});

Route::get('/admin/cards', function() {
    return view('admin.cards');
})->name('admin.cards.index');

Route::get('/admin', function () {
    return file_get_contents(public_path('admin.html'));
});

// Route "catch-all" pour que le rafraîchissement (F5) fonctionne sur des sous-pages
Route::get('/dashboard', function () {
    return file_get_contents(public_path('index.html'));
})->name('dashboard');

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');


/*
// Routes d'inscription améliorée avec parrainage et sélection de langue
Route::get('/register/enhanced', [EnhancedRegistrationController::class, 'create'])
    ->name('register.enhanced');

Route::post('/register/enhanced', [EnhancedRegistrationController::class, 'store'])
    ->name('register.enhanced.store');

Route::get('/register/ref/{code}', [EnhancedRegistrationController::class, 'createWithReferral'])
    ->name('register.referral')
    ->where('code', 'REF-[A-Z0-9]{6}');

// Route de redirection pour les liens de parrainage courts
Route::get('/ref/{code}', function ($code) {
    return redirect()->route('register.enhanced', ['ref' => $code]);
})->name('referral.redirect')->where('code', 'REF-[A-Z0-9]{6}');
*/
