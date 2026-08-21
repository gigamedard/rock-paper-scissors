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
            'pool_id' => 0,
            'salt' => bin2hex(random_bytes(32)),
            'pool_size' => $this->faker->randomElement($poolSizes),
            'base_bet' => $this->faker->randomFloat(8, 0.00000001, 1),
            'premove_cids' => null,
            'status' => 'from_server_waitting',
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
            $pool->pool_id = $pool->id;
            $pool->save();
        });
    }

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

    public function batched(): Factory
    {
        return $this->state(fn () => ['status' => 'batched']);
    }
}
