<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper; // Assuming you have a Web3Helper class for sorting

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
        'status',
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
        Log::info('=========> Starting match() for pool ID: ' . $this->id);

        Web3Helper::marker(20,"model pool", "match","after ===> Starting match() for pool ID: " . $this->id);
    
        // Step 1: Load available users from this pool
        $availableUsers = $this->users;
    
        // Step 2: Verify balance and adjust balances
        $availableUsers = $availableUsers->filter(function ($user) {
            if ($user->balance >= $this->base_bet) {
                $user->balance -= $this->base_bet;
                $user->battle_balance += $this->base_bet;
                $user->save();
                return true;
            }
            return false; // exclude user if balance is insufficient
        })->values(); // reset keys after filtering
    
        Log::info('=========> Available users after balance check: ' . json_encode($availableUsers->pluck('wallet_address')));
        Web3Helper::marker(20,"model pool", "match","after ===> Available users after balance check: " . json_encode($availableUsers->pluck('wallet_address')));
    
        // Step 3: Get sorted wallet addresses
        $sortedWallets = Web3Helper::sortAddressesWithSalt(
            $availableUsers->pluck('wallet_address')->toArray(),
            $this->salt
        );
    
        // Step 4: Sort the users to match wallet hash order
        $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedWallets) {
            return array_search($user->wallet_address, $sortedWallets);
        })->values();
    
        Log::info('=========> Users sorted by wallet address hash: ' . json_encode($availableUsers->pluck('wallet_address')));
        Web3Helper::marker(20,"model pool", "match","after ===> Users sorted by wallet address hash: " . json_encode($availableUsers->pluck('wallet_address')));
    
        // Step 5: Ensure we have an even number of users for pairing
        if ($availableUsers->count() % 2 !== 0) {
            $removedUser = $availableUsers->pop(); // remove the last user
            Log::info('=========> Removed user for uneven count: ' . $removedUser->wallet_address);
            Web3Helper::marker(20,"model pool", "match","after ===> Removed user for uneven count: " . $removedUser->wallet_address);
        }

        // Step 6: Pair users and create fights
        for ($i = 0; $i < $availableUsers->count(); $i += 2) {
            $user1 = $availableUsers[$i];
            $user2 = $availableUsers[$i + 1];
    
            $fight = Fight::create([
                'pool_id' => $this->id,
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
                'base_bet_amount' => $this->base_bet,
                'status' => 'waiting_for_result',
            ]);
    
            Log::info("=========> Fight created between {$user1->wallet_address} and {$user2->wallet_address}");
            Web3Helper::marker(20,"model pool", "match","after ===> Fight created between {$user1->wallet_address} and {$user2->wallet_address}");
    
            // Trigger gameplay logic
            $fight->handlePoolAutoplayFight($this->base_bet, $this->pool_size);
        }
        
    
        Log::info('=========> match() complete for pool ID: ' . $this->id);
        Web3Helper::marker(20,"model pool", "match","after ===> match() complete for pool ID: " . $this->id);
    }
    
}