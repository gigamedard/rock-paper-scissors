<?php
$user = App\Models\User::find(33);
$influencer = $user->influencer;

$event = new App\Events\InfluencerRewardClaimed($influencer, 100.0);
$listener = new App\Listeners\ProcessInfluencerRewardPayment();

try {
    $listener->handle($event);
    dump("Payment processed successfully!");
} catch (\Exception $e) {
    dump("Error: " . $e->getMessage());
}
