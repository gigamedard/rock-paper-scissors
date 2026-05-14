<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class SeedAdminUser extends Seeder
{
    public function run(): void
    {
        $adminAddress = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
        
        $user = User::updateOrCreate(
            ['wallet_address' => $adminAddress],
            [
                'name' => 'SuperAdmin',
                'is_admin' => true
            ]
        );

        echo "User #0 ($adminAddress) is now Admin.\n";
    }
}
