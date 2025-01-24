<?php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\PoolAutoMatchController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/play', [GameController::class, 'play']);
});



Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
Route::middleware('api')->group(function () {
    Route::post('/update-counter', [BlockchainController::class, 'updateCounter']);
});




