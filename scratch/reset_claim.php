<?php
$wallet = '0x70997970C51812dc3A010C7d01b50e0d17dc79C8';
$user = App\Models\User::where('wallet_address', $wallet)->orWhere('wallet_address', strtolower($wallet))->first();

if ($user) {
    $influencer = $user->influencer;
    if ($influencer) {
        $pool = $influencer->pool;
        
        // Reset the claim status
        $influencer->update(['has_claimed' => false]);
        
        // Ensure personal milestone is reached
        $influencer->stats->update(['referral_count' => max($influencer->stats->referral_count, $pool->milestone)]);
        
        // Ensure pool milestone is reachable
        $pool->update([
            'milestone' => 5,
            'pool_milestone' => 50,
            'reward_amount' => 100
        ]);
        
        // Make sure the total pool count >= 50
        $influencer->stats->update(['referral_count' => 50]);

        dump("Success! Influencer " . $user->name . " (ID: " . $user->id . ") can now claim the reward.");
        dump("Go to the UI and click the Claim button.");
    } else {
        dump("Error: User " . $user->id . " is not an influencer.");
    }
} else {
    dump("Error: User with wallet " . $wallet . " not found.");
}
