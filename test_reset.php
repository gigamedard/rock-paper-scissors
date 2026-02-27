<?php
$u = App\Models\User::find(1);
if ($u) {
    $u->autoplay_active = false;
    $u->status = 'available';
    $u->save();
    echo "DB Reset OK\n";
} else {
    echo "User not found\n";
}
