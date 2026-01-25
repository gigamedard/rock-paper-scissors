<?php

use App\Models\User;

$address = '***REMOVED***';
$user = User::where('wallet_address', $address)->first();

if (!$user) {
    // Try adding 0x
    $address0x = '0x' . $address;
    $user = User::where('wallet_address', $address0x)->first();
}

if ($user) {
    $user->is_admin = true;
    $user->save();
    echo "User found (ID: {$user->id}, Address: {$user->wallet_address}) and set to admin.\n";
} else {
    echo "User NOT FOUND with address: $address (or with 0x prefix).\n";
    // Check if it's a private key and we can derive the address? 
    // Actually, maybe the user GAVE me the private key and wants me to find the address.
    // I can try to use a node script to derive address from private key if needed.
}
