<?php
$pool = App\Models\InfluencerPool::first();
if ($pool) {
    $pool->reward_amount = 10; // Change to 10 ETH so the contract can pay it out (it has 51.25 ETH)
    $pool->save();
}

$user = App\Models\User::find(1);
$influencer = $user->influencer;
if ($influencer) {
    $influencer->has_claimed = false;
    $influencer->save();
}
echo "Pool reward set to 10. User claim status reset to false.\n";
