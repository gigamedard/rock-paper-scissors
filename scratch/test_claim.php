<?php
// Simulate reaching milestone
$user = App\Models\User::find(33); // Influencer
$influencer = $user->influencer;
$pool = $influencer->pool;

// Boost pool stats and influencer stats
$pool->update(['milestone' => 5, 'pool_milestone' => 50, 'reward_amount' => 100]);
$influencer->stats->update(['referral_count' => 50]); // Reaches personal milestone and pool milestone

dump("Pool global milestone reached? " . ($pool->isPoolMilestoneReached() ? 'yes' : 'no'));
dump("Influencer can claim? " . ($influencer->canClaimReward() ? 'yes' : 'no'));

// Simulate HTTP request for claimReward
Auth::login($user);
$controller = app(\App\Http\Controllers\InfluencerController::class);
$response = $controller->claimReward();

dump("Response: " . $response->getContent());
