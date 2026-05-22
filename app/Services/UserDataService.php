<?php

namespace App\Services;

use App\Models\User;

class UserDataService
{
    public function registerForAutoplay(int $userId, $bet_amount, $target_q = 2.0, $cooldown_time = 1440)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        $user->update([
            'autoplay_active' => true,
            'bet_amount' => $bet_amount,
            'status' => 'available',
            'target_q' => $target_q,
            'cooldown_time' => $cooldown_time,
        ]);
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
