<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the super admin user (no company, no branch).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'musmannadeem92@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'phone' => null,
                'type' => 'super_admin',
                'status' => 'active',
                'can_login' => true,
                'company_id' => null,
                'branch_id' => null,
            ]
        );
    }
}
