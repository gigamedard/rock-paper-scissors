<?php

namespace Database\Factories;

use App\Models\Pool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Log; // Optional: For logging if needed

class PoolFactory extends Factory
{
    protected $model = Pool::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $poolSizes = config('pool.size', [100, 500, 1000, 5000]);

        return [
            // 'pool_id' is removed from here. It will be set in afterCreating.
            'pool_id' => 0, // Keep generating unique salt
            'salt' => $this->faker->unique()->lexify('??????'), // Keep generating unique salt
            'pool_size' => $this->faker->randomElement($poolSizes),
            'base_bet' => $this->faker->randomFloat(8, 0.00000001, 1),
            'premove_cids' => null,
            'status' => 'from_server_waitting',
            // 'id' is correctly omitted, allowing the database to generate the primary UUID
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Pool $pool) {
            // After the pool is created and has an ID, update pool_id to match it.
            $pool->pool_id = $pool->id;
            $pool->save(); // Save the change
            // Optional: Log::info("Set pool_id to match id for Pool {$pool->id}");
        });
    }

    // --- Keep your existing states (running, finished) ---

    public function running(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'from_server_running',
        ]);
    }

    public function finished(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'from_server_finished',
        ]);
    }
}