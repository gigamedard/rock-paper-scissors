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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PoolAutoMatchController;

use Illuminate\Support\Str;
use kornrunner\Keccak;

use App\Models\Challenge;
use App\Models\Pool;
use App\Models\User;
use App\Events\testevent;
use App\Helpers\Web3Helper;

use App\Events\BalanceUpdated;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/counter', function (Request $request) {
    // Validate the incoming request
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
    return response()->json(['message' => 'Counter updated successfully'], 200);
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
    return view('ipfs');
});


Route::get('/testevent', function () {

    /*$challenge = new Challenge();
    $challenge->sender_id = Auth::id();
    $challenge->receiver_id = 2;
    $challenge->status = 'pending';
    $challenge->save();*/
    event(new BalanceUpdated(1, 8));
    event(new testevent(1,8.8));
    

    return view('welcome');
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



Route::post('/test-pinata-upload', function (Request $request) {
    $controller = new PoolAutoMatchController();
    return response()->json([
        'cid' => $controller->uploadPreMoveToPinata($request->premove_data),
    ]);
});













Route::get('/get_server_time', function () {
    $timestamp = now()->timestamp; // Get the current timestamp
    return response()->json(['timestamp' => $timestamp]);
});

Route::get('/csrf-token', function () {
    Log::info('started csrf-token');
    return response()->json(['csrfToken' => csrf_token()]);
});






// Dashboard route
Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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




// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// autoprocess routes

Route::get('/triggermatching', [AutoMatchController::class, 'selectSliceInstenceForAllBetAmount']);// later add a unique use token in parameter for security
Route::post('/user/pre-moves', [AutoMatchController::class, 'storePreMoves']);
Route::get('/autoplay', [AutoMatchController::class, 'index']);
Route::middleware(['auth'])->post('/user/pre-moves', [AutoMatchController::class, 'storePreMoves']);


Route::get('/test-handle-pool-event', [PoolAutoMatchController::class, 'testHandlePoolEmitedEvent']);

// wallet routes
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
//Route::post('/verify-signature', [WalletAuthController::class, 'verifySignature'])->middleware('throttle:10,1'); // 10 requests per minute
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);
Route::post('/wallet/logout', [WalletAuthController::class, 'logout']);
Route::get('/wallet/testrecovery', [WalletAuthController::class, 'testRecovery']);
Route::get('/update-counter', [BlockchainController::class, 'updateCounter']);
Route::get('/update-balance', [BlockchainController::class, 'updateUserBalance']);
Route::get('/artefacts', [BlockchainController::class, 'getArtefacts']);
Route::get('/handle-pool-emited', [PoolAutoMatchController::class, 'poolEmitedRequest']);

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

    








// Auth routes
require __DIR__.'/auth.php';
