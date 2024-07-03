<?php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/play', [GameController::class, 'play']);
});
