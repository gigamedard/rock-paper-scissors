<?php
// DB user state check
use App\Models\User;
foreach (User::orderBy('id')->get(['id', 'email', 'status', 'token_balance', 'balance', 'bet_amount']) as $u) {
    echo $u->id . ' | ' . $u->email . ' | ' . $u->status . ' | token_balance=' . ($u->token_balance ?? 'n/a') . ' | balance=' . number_format((float)$u->balance, 3) . ' | bet=' . number_format((float)$u->bet_amount, 2) . PHP_EOL;
}