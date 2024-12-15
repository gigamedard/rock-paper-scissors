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

use App\Models\Challenge;
use App\Models\User;
use App\Events\testevent;


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


Route::get('/autoplay2', function () {
    return view('autoplay2');
});


Route::get('/autoplay3', function () {
    return view('autoplay3');
});


Route::get('/testevent', function () {

    $challenge = new Challenge();
    $challenge->sender_id = Auth::id();
    $challenge->receiver_id = 2;
    $challenge->status = 'pending';
    $challenge->save();

    event(new testevent());

    return view('welcome');
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

// wallet routes
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
//Route::post('/verify-signature', [WalletAuthController::class, 'verifySignature'])->middleware('throttle:10,1'); // 10 requests per minute
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);
Route::post('/wallet/logout', [WalletAuthController::class, 'logout']);
Route::get('/wallet/testrecovery', [WalletAuthController::class, 'testRecovery']);
Route::get('/update-counter', [BlockchainController::class, 'updateCounter']);
Route::get('/update-balance', [BlockchainController::class, 'updateUserBalance']);



    








// Auth routes
require __DIR__.'/auth.php';
