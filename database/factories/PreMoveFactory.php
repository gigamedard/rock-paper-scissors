<?php

namespace Database\Factories;

use App\Models\PreMove;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreMoveFactory extends Factory
{
    protected $model = PreMove::class;

    public function definition()
    {
        return [
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
            'nonce' => 'test_nonce',
            'current_index' => 0,
            'session_first_pool_id' => 0,
            'cid' => 'test_cid',
        ];
    }
}
