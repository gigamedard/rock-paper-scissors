<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class SeedAdminUser extends Seeder
{
    public function run(): void
    {
        // SECURITY: admin address from env, fallback to Hardhat #0 only in local/testing.
        $adminAddress = env('ADMIN_WALLET_ADDRESS');

        if (!$adminAddress) {
            if (app()->environment('local', 'testing')) {
                $adminAddress = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
            } else {
                throw new \RuntimeException(
                    'ADMIN_WALLET_ADDRESS is not set. Refusing to seed an admin with a publicly-known key in this environment.'
                );
            }
        }

        $adminAddress = strtolower($adminAddress);

        $user = User::updateOrCreate(
            ['wallet_address' => $adminAddress],
            [
                'name' => 'SuperAdmin',
                'email' => 'admin@battlepool.io',
                'password' => bcrypt(env('ADMIN_PASSWORD', 'password')),
                'is_admin' => true
            ]
        );

        echo "User ($adminAddress) is now Admin.\n";
    }
}
