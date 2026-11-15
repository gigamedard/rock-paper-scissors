<?php

namespace App\Services;

use App\Models\User;

class UserDataService
{
    public function registerForAutoplay(int $userId, $bet_amount, $target_q = 2.0, $cooldown_time = 1440, bool $autoplay = true)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        $update = [
            'autoplay_active' => $autoplay,
            'bet_amount' => $bet_amount,
            'initial_base_bet' => $bet_amount,
            'status' => 'available',
            'cooldown_time' => $cooldown_time,
        ];
        // La cible d'un bankroll ACTIF est immuable : ne pas l'écraser avec le
        // sélecteur d'une nouvelle session (bug 1.2 vs 2.0, 2026-11-15).
        if (!$user->bankroll_total && !$user->session_started) {
            $update['target_q'] = $target_q;
        }
        $user->update($update);
    }

    public function unregisterFromAutoplay($user)
    {
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        $user->update([
            'autoplay_active' => false,
            'status' => 'available',
        ]);
    }
}
