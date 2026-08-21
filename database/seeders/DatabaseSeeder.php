<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SeedAdminSettings::class,
        ]);

        // SECURITY: the admin wallet address must come from the environment
        // (ADMIN_WALLET_ADDRESS), NOT be hardcoded to the publicly-known
        // Hardhat #0 account. Hardhat #0 is only a fallback for local/testing.
        $adminAddress = env('ADMIN_WALLET_ADDRESS');

        if (!$adminAddress) {
            if (app()->environment('local', 'testing')) {
                // Local/test fallback only — never in production.
                $adminAddress = '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266';
            } else {
                throw new \RuntimeException(
                    'ADMIN_WALLET_ADDRESS is not set. Refusing to seed an admin with a publicly-known key in this environment.'
                );
            }
        }

        $adminAddress = strtolower($adminAddress);

        User::updateOrCreate(
            ['wallet_address' => $adminAddress],
            [
                'name' => 'Admin',
                'email' => 'admin@battlepool.com',
                'password' => \Illuminate\Support\Facades\Hash::make(env('ADMIN_PASSWORD', 'password123')),
                'is_admin' => 1,
                'referral_code' => 'REF-ADMIN0',
                'balance' => 1000.0,
            ]
        );
    }
}
