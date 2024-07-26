<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Challenge;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


/*Broadcast::channel('challengechannel.{challId}', function (User $user, $challId) {
     $challenge = $user->challenge();
    return (int) $challenge->id === (int) $challId;
});*/

