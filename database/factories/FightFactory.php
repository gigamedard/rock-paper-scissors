<?php

namespace Database\Factories;

use App\Models\Fight;
use Illuminate\Database\Eloquent\Factories\Factory;

class FightFactory extends Factory
{
    protected $model = Fight::class;

    public function definition()
    {
        return [
            'user1_id' => User::factory(),
            'user2_id' => User::factory(),
            'bet_amount' => $this->faker->randomElement([1,2, 4, 8,16, 32,64,128,256,512,1024]),
            'status' => 'waiting_for_both',
        ];
    }
}

