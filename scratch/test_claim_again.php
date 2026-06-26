<?php
$user = App\Models\User::find(33);
$influencer = $user->influencer;
$influencer->update(['has_claimed' => false]);

Auth::login($user);
$controller = app(\App\Http\Controllers\InfluencerController::class);
$response = $controller->claimReward();

dump("Response: " . $response->getContent());
