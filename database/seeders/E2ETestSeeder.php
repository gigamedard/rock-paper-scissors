<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\PreMove;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds 4 test users with Hardhat default account addresses and pre-moves.
 * Usage: php artisan db:seed --class=E2ETestSeeder
 */
class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        // Hardhat default accounts (deterministic)
        $accounts = [
            [
                'address' => '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
                'name'    => 'Alice',
                'moves'   => ['rock', 'paper', 'scissors', 'rock', 'paper'],
                'cid'     => 'cid_alice_e2e_test',
            ],
            [
                'address' => '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
                'name'    => 'Bob',
                'moves'   => ['scissors', 'rock', 'paper', 'scissors', 'rock'],
                'cid'     => 'cid_bob_e2e_test',
            ],
            [
                'address' => '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
                'name'    => 'Charlie',
                'moves'   => ['paper', 'scissors', 'rock', 'paper', 'scissors'],
                'cid'     => 'cid_charlie_e2e_test',
            ],
            [
                'address' => '0x90F79bf6EB2c4f870365E785982E1f101E93b906',
                'name'    => 'Diana',
                'moves'   => ['rock', 'rock', 'rock', 'rock', 'rock'],
                'cid'     => 'cid_diana_e2e_test',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['wallet_address' => $account['address']],
                [
                    'name'            => $account['name'],
                    'email'           => strtolower($account['name']) . '@e2etest.local',
                    'password'        => Hash::make('password'),
                    'balance'         => 100.0,
                    'battle_balance'  => 0,
                    'bet_amount'      => 0.001,
                    'status'          => 'available',
                    'autoplay_active' => true,
                    'session_started' => false,
                ]
            );

            PreMove::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'moves'         => $account['moves'],
                    'cid'           => $account['cid'],
                    'current_index' => 0,
                    'hashed_moves'  => null,
                ]
            );

            $this->command->info("✅ User {$account['name']} (ID:{$user->id}) → {$account['address']}");
        }

        $this->command->info("\n🎯 4 test users seeded with pre-moves. Ready for E2E test!");
        $this->command->info("   CIDs: cid_alice_e2e_test, cid_bob_e2e_test, cid_charlie_e2e_test, cid_diana_e2e_test");
    }
}
