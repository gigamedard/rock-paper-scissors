<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| C'est ici que le frontend (la SPA) est servi.
| Toutes les autres routes (API, etc.) sont dans routes/api.php.
|
*/

Route::get('/', function () {
    // Renvoie le fichier HTML principal de ton application statique
    return file_get_contents(public_path('index.html'));
});

// Optionnel : Une route "catch-all" si tu utilises le "History Mode"
// Route::get('/{any}', function () {
//     return file_get_contents(public_path('index.html'));
// })->where('any', '.*');

