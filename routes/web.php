<?php
use App\Http\Controllers\GameController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/game', [GameController::class, 'index']);
Route::post('/game/play', [GameController::class, 'play']);

