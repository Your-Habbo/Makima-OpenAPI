<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        UserProfile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
            ]
        );

        $admin->assignRole('admin');

        // Create a regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Test User',
                'username' => 'testuser',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
            ]
        );

        $user->assignRole('user');
    }
}