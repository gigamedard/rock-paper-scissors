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

        // Seed Account #0 as Admin
        User::updateOrCreate(
            ['wallet_address' => '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266'],
            [
                'name' => 'Admin Hardhat #0',
                'email' => 'admin@battlepool.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'is_admin' => 1,
                'referral_code' => 'REF-ADMIN0',
                'balance' => 1000.0,
            ]
        );
    }
}
