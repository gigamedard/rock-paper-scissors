<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Pool;
use App\Models\User;
use App\Models\Fight;



class Pool extends Model
{   
    use HasFactory;
    protected $table = 'pools';

    protected $fillable = [
        'salt',
        'pool_size',
        'pool_id',
        'base_bet',
        'premove_cids',
    ];

    // Define the many-to-many relationship with users
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pool_id');
    }
    // A pool has many fights
    public function fights(): HasMany
    {
        return $this->hasMany(Fight::class);
    }

    private function removeUserFromPool(int $userId): void
    {

        $this->users()->detach($userId);
    }

    public function match(): void
    {
            // Get available users from this pool
            $availableUsers = $this->users;

            // Verify balance and update for each user
            foreach ($availableUsers as $user) {
                if ($user->balance >= $this->base_bet) {
                    $user->balance -= $this->base_bet;
                    $user->battle_balance += $this->base_bet;
                    $user->save();
                } else {
                    // Remove user from the collection if balance is insufficient
                    $availableUsers = $availableUsers->reject(function ($u) use ($user) {
                        return $u->id === $user->id;
                    });
                }
            }

            Log::info('===================================>availableUsers befor sorting: ' . json_encode($availableUsers));
            // sort the users by their wallet address hashed with salt

            $sortedUsers = Web3Helper::sortAddressesWithSalt($availableUsers->pluck('wallet_address')->toArray(), $pool->salt);
            $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedUsers) {
                return array_search($user->wallet_address, $sortedUsers);
            })->values();


            Log::info('===================================>availableUsers after sorting: ' . json_encode($availableUsers));


            // Ensure even count of users
            if ($sortedUsers->count() % 2 !== 0) {
                $sortedUsers->pop();
            }

            // Generate fights and process them
            if ($sortedUsers->isNotEmpty()) {
                for ($i = 0; $i < $sortedUsers->count(); $i += 2) {
                    $fight = Fight::create([
                        'user1_id' => $sortedUsers[$i]->id,
                        'user2_id' => $sortedUsers[$i + 1]->id,
                        'base_bet_amount' => $this->base_bet,
                        'status' => 'waiting_for_result',
                    ]);

                    // Use a different method to complete the pool fight
                    $fight->handlePoolAutoplayFight($this->baseBet, $this->poolSize);
                }
            }
        
    }
}