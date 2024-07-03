<?php
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard route
Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Challenge routes
Route::middleware(['auth'])->group(function () {
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');
    Route::post('/challenge/send/{user}', [ChallengeController::class, 'sendChallenge'])->name('challenge.send');
    Route::post('/challenge/accept/{invitation}', [ChallengeController::class, 'acceptChallenge'])->name('challenge.accept');
});

// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Auth routes
require __DIR__.'/auth.php';
