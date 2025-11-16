<?php

namespace App\Services;

use App\Models\User;

class UserDataService
{
    public function registerForAutoplay(int $userId, $bet_amount)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        $user->update([
            'autoplay_active' => true,
            'bet_amount' => $bet_amount,
            'status' => 'available',
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
