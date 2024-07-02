<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::post('/challenges', [ChallengeController::class, 'store'])->middleware(['auth'])->name('challenges.store');
Route::put('/challenges/{challenge}', [ChallengeController::class, 'update'])->middleware(['auth'])->name('challenges.update');

require __DIR__.'/auth.php';
