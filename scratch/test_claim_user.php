<?php
$user = App\Models\User::where('wallet_address', 'like', '0xf39Fd6e51%')->first();
if (!$user) {
    echo "User not found\n";
    return;
}
echo "User ID: {$user->id}\n";

$pool = App\Models\InfluencerPool::first();
if (!$pool) {
    $pool = new App\Models\InfluencerPool();
    $pool->name = 'Test Pool';
    $pool->language = 'en';
    $pool->milestone = 5;
    $pool->pool_milestone = 50;
    $pool->reward_amount = 100;
    $pool->is_active = true;
    $pool->save();
} else {
    $pool->milestone = 5;
    $pool->pool_milestone = 50;
    $pool->reward_amount = 100;
    $pool->save();
}

$influencer = App\Models\Influencer::where('user_id', $user->id)->first();
if (!$influencer) {
    echo "User is not an influencer, creating one...\n";
    $influencer = new App\Models\Influencer();
    $influencer->user_id = $user->id;
    $influencer->pool_id = $pool->id;
    $influencer->is_eligible = true;
    $influencer->has_claimed = false;
    $influencer->save();
} else {
    $influencer->is_eligible = true;
    $influencer->has_claimed = false;
    $influencer->save();
}

$stats = App\Models\InfluencerStat::where('influencer_id', $influencer->id)->first();
if (!$stats) {
    $stats = new App\Models\InfluencerStat();
    $stats->influencer_id = $influencer->id;
    $stats->referral_count = 50;
    $stats->total_avax_spent = 1000;
    $stats->save();
} else {
    $stats->referral_count = 50;
    $stats->total_avax_spent = 1000;
    $stats->save();
}

echo "Milestones and stats updated!\n";
try {
    echo "Pool global milestone reached: " . ($pool->isPoolMilestoneReached() ? 'Yes' : 'No') . "\n";
    echo "Influencer can claim reward: " . ($influencer->canClaimReward() ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Could not check eligibility via models: " . $e->getMessage() . "\n";
}
