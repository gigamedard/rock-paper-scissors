<?php
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FightController;
use App\Http\Controllers\UserSettingsController;

use App\Models\Challenge;
use App\Models\User;
use App\Events\testevent;


Route::get('/', function () {
    return view('welcome');
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





// Dashboard route
Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Challenge routes
Route::middleware(['auth'])->group(function () {
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');
    Route::post('/challenge/send/{userId}/{baseBetAmount}', [ChallengeController::class, 'sendChallenge'])->name('challenge.send');
    Route::post('/challenge/accept/{invitationId}', [ChallengeController::class, 'acceptChallenge'])->name('challenge.accept');
});



// Fight routes
Route::middleware(['auth'])->group(function () {
    Route::post('/fight/{fightId}/{selectedMove}', [FightController::class, 'played'])->name('Fight.played');
    
});

// Users Settings routes
Route::middleware('auth')->group(function() {
    Route::get('/user/settings', [UserSettingsController::class, 'getUserSettings']);
    Route::post('/user/settings', [UserSettingsController::class, 'saveUserSettings']);
});




// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Auth routes
require __DIR__.'/auth.php';
