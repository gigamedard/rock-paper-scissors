<?php

// script to update all bots' bet_amount to a specific base bet
// Usage: php artisan tinker scratch/update_bots_bet.php

$baseBet = (float) (getenv('BASE_BET') ?: 0.05);

$updated = \App\Models\User::where('autoplay_active', true)
    ->update([
        'bet_amount' => $baseBet,
        'initial_base_bet' => $baseBet
    ]);

echo "Updated {$updated} bots to bet_amount = {$baseBet}\n";
exit;
