<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users', 'create users', 'edit users', 'delete users',
            
            // Product permissions
            'view products', 'create products', 'edit products', 'delete products',
            
            // Order permissions
            'view orders', 'create orders', 'edit orders', 'delete orders',
            
            // Content management
            'manage content', 'manage categories', 'manage pages',
            
            // Settings
            'manage settings',
            
            // Reports
            'view reports', 'generate reports',
            
            // Forum/Group permissions
            'manage forums', 'manage groups', 'moderate content',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign created permissions
        
        // Unverified role - for new users who haven't selected a role yet
        Role::firstOrCreate(['name' => 'unverified']);
        
        // Admin - gets all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Artisan role
        $artisanRole = Role::firstOrCreate(['name' => 'artisan']);
        $artisanRole->givePermissionTo([
            'view products', 'create products', 'edit products', 'delete products',
            'view orders', 'create orders', 'edit orders',
            'manage content', 'manage forums', 'manage groups'
        ]);

        // Buyer role
        $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
        $buyerRole->givePermissionTo([
            'view products', 'create orders', 'view orders',
            'manage content', 'manage forums', 'manage groups'
        ]);

        // Marketer role
        $marketerRole = Role::firstOrCreate(['name' => 'marketer']);
        $marketerRole->givePermissionTo([
            'view users', 'view products', 'view orders',
            'manage content', 'view reports', 'generate reports'
        ]);
    }
}