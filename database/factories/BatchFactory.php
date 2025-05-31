<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        // Get possible sizes from config to pick one
        $poolSizes = config('pool.size', [100, 500, 1000, 5000]);

        return [
            'pool_size' => $this->faker->randomElement($poolSizes),
            'first_pool_id' => 0, // Default, override in tests
            'last_pool_id' => 0,  // Default, override in tests
            'number_of_pools' => 0, // Default, override in tests
            'max_size' => config('pool.batch_max_size', 100),
            'status' => 'waiting',
            'iteration_count' => 0,
            'max_iterations' => config('pool.batch_max_iterations', 5),
            // created_at, updated_at are handled automatically
        ];
    }

    // Add states for different statuses
    public function running(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
        ]);
    }

    public function settled(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'settled',
        ]);
    }
}