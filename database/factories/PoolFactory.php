<?php
namespace Database\Factories;

use App\Models\Pool;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoolFactory extends Factory
{
    protected $model = Pool::class;

    public function definition()
    {
        return [
            'salt' => bin2hex(random_bytes(16)), // Random salt
            'pool_size' => $this->faker->numberBetween(2, 10), // Example pool size
            'pool_id' => $this->faker->unique()->uuid, // Unique identifier for the pool
            'base_bet' => $this->faker->randomFloat(2, 1, 100), // Example base bet amount
        ];
    }
}