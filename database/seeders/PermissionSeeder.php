<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            ['name' => 'users.view', 'label' => 'View Users', 'description' => 'View user list and details'],
            ['name' => 'users.create', 'label' => 'Create Users', 'description' => 'Create new users'],
            ['name' => 'users.edit', 'label' => 'Edit Users', 'description' => 'Edit user information'],
            ['name' => 'users.delete', 'label' => 'Delete Users', 'description' => 'Delete users'],

            ['name' => 'wallets.view', 'label' => 'View Wallets', 'description' => 'View wallet list and details'],
            ['name' => 'wallets.create', 'label' => 'Create Wallets', 'description' => 'Create new wallets'],
            ['name' => 'wallets.delete', 'label' => 'Delete Wallets', 'description' => 'Delete wallets'],

            ['name' => 'transactions.view', 'label' => 'View Transactions', 'description' => 'View transaction list and details'],
            ['name' => 'transactions.process', 'label' => 'Process Transactions', 'description' => 'Process and broadcast transactions'],

            ['name' => 'tokens.view', 'label' => 'View Tokens', 'description' => 'View token list and details'],
            ['name' => 'tokens.create', 'label' => 'Create Tokens', 'description' => 'Create new tokens'],
            ['name' => 'tokens.edit', 'label' => 'Edit Tokens', 'description' => 'Edit token information'],
            ['name' => 'tokens.delete', 'label' => 'Delete Tokens', 'description' => 'Delete tokens'],
            ['name' => 'tokens.toggle', 'label' => 'Toggle Token Status', 'description' => 'Enable or disable tokens'],

            ['name' => 'staking.view', 'label' => 'View Staking', 'description' => 'View staking plans and positions'],
            ['name' => 'staking.manage', 'label' => 'Manage Staking', 'description' => 'Manage staking configuration'],
            ['name' => 'staking.execute', 'label' => 'Execute Staking', 'description' => 'Execute staking operations'],

            ['name' => 'ico.view', 'label' => 'View ICO', 'description' => 'View ICO campaigns'],
            ['name' => 'ico.manage', 'label' => 'Manage ICO', 'description' => 'Manage ICO campaigns'],
            ['name' => 'ico.execute', 'label' => 'Execute ICO', 'description' => 'Execute ICO operations'],

            ['name' => 'reports.view', 'label' => 'View Reports', 'description' => 'View reports and analytics'],
            ['name' => 'reports.export', 'label' => 'Export Reports', 'description' => 'Export reports to files'],

            ['name' => 'settings.view', 'label' => 'View Settings', 'description' => 'View system settings'],
            ['name' => 'settings.manage', 'label' => 'Manage Settings', 'description' => 'Manage system settings'],

            ['name' => 'admin.access', 'label' => 'Admin Access', 'description' => 'Access admin panel and logs'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['label' => 'Super Admin', 'is_super_admin' => true]
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Admin', 'is_super_admin' => false]
        );

        $staff = Role::firstOrCreate(
            ['name' => 'staff'],
            ['label' => 'Staff', 'is_super_admin' => false]
        );

        // Assign all permissions to super_admin
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Assign all permissions except super_admin-only to admin
        $adminPermissions = Permission::whereNotIn('name', [])->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        // Assign view permissions to staff
        $staffPermissions = Permission::whereIn('name', [
            'users.view',
            'wallets.view',
            'transactions.view',
            'tokens.view',
            'reports.view',
        ])->pluck('id');
        $staff->permissions()->sync($staffPermissions);
    }
}