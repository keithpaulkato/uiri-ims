<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles, permissions, and the role->permission mappings using
     * spatie/laravel-permission models.
     *
     * Source: database.sql (legacy app) — "Seed roles and permissions" block.
     */
    public function run(): void
    {
        $roles = [
            'Administrator' => 'Full system access across all branches',
            'Campus Manager' => 'Manage operations for assigned campus',
            'Store Manager' => 'Manage inventory and stock for assigned branch',
            'Section Manager' => 'Manage section inventory and requests',
            'Staff' => 'View inventory and request items',
            'Department Head' => 'Oversee department operations and approve requests',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        $permissions = [
            'manage_users' => 'Create, edit and deactivate users',
            'manage_permissions' => 'Create and assign permissions to roles/users',
            'view_dashboard' => 'Access dashboard and KPI cards',
            'manage_inventory' => 'Add, edit, delete inventory items',
            'manage_stock' => 'Perform stock in/out and adjustments',
            'manage_requests' => 'Create and manage inventory requests',
            'approve_requests' => 'Approve or reject requests',
            'manage_transfers' => 'Initiate and approve transfers between campuses',
            'manage_suppliers' => 'Add and manage suppliers',
            'view_reports' => 'Access reporting and exports',
            'view_audit' => 'View audit logs',
            'manage_sections' => 'Create and edit sections',
            'manage_departments' => 'Create and edit departments',
            'manage_assets' => 'Register and manage assets',
            'manage_maintenance' => 'Schedule and track maintenance',
            'manage_settings' => 'Edit system settings',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        $rolePermissionMap = [
            // Administrator -> all permissions
            'Administrator' => array_keys($permissions),
            'Campus Manager' => ['view_dashboard', 'manage_sections', 'manage_departments', 'view_reports', 'manage_transfers'],
            'Store Manager' => ['manage_inventory', 'manage_stock', 'manage_requests', 'approve_requests', 'manage_suppliers'],
            'Section Manager' => ['manage_requests', 'view_dashboard', 'manage_sections'],
            'Department Head' => ['approve_requests', 'manage_departments', 'view_reports'],
            'Staff' => ['manage_requests', 'view_dashboard'],
        ];

        foreach ($rolePermissionMap as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
            $role->givePermissionTo($permissionNames);
        }
    }
}
