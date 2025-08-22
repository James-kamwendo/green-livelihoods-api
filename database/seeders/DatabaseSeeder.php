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
        // Create roles and permissions first
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@greenlivelihoods.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        
        // Assign admin role
        $admin->assignRole('admin');

        // Create sample users for other roles
        $artisan = User::factory()->create([
            'name' => 'Artisan User',
            'email' => 'artisan@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $artisan->assignRole('artisan');

        $buyer = User::factory()->create([
            'name' => 'Buyer User',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $buyer->assignRole('buyer');

        $marketer = User::factory()->create([
            'name' => 'Marketer User',
            'email' => 'marketer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $marketer->assignRole('marketer');
    }
}
