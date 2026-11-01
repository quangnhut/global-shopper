<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('auth.providers.users.model');

        $admin = $userModel::query()->create([
            'first_name' => 'Shopper',
            'last_name' => 'Admin',
            'email' => 'admin@laravelshopper.dev',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ]);

        $admin->assignRole(config('shopper.admin.roles.admin'));
    }
}
