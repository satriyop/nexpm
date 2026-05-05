<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@nexpm.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'role' => Role::SuperAdmin,
                'main_contractor_id' => null,
                'subcontractor_id' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
