<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Seed the 3 default users.
     *
     * NOTE (deviation from database.sql): the original database.sql seeded
     * jssemanda and gakello with role_id=2 (Campus Manager). The approved
     * project spec instead lists both as Store Managers, so this seeder
     * follows the spec and assigns them the 'Store Manager' role/role_id.
     *
     * Users also get syncRoles() called on them so the Spatie HasRoles
     * trait (added to the User model in Task 7) recognizes their role,
     * in addition to the legacy `role` (string) and `role_id` columns
     * which are kept for backwards compatibility with the pre-Spatie
     * users table shape.
     */
    public function run(): void
    {
        $users = [
            [
                'username' => 'admin',
                'full_name' => 'System Administrator',
                'email' => 'admin@uiri.go.ug',
                'phone' => '+256 700 000001',
                'branch_id' => 1, // UIRI Nakawa (HQ)
                'role' => 'Administrator',
            ],
            [
                'username' => 'jssemanda',
                'full_name' => 'John Ssemanda',
                'email' => 'jssemanda@uiri.go.ug',
                'phone' => '+256 700 000002',
                'branch_id' => 1, // UIRI Nakawa (HQ)
                'role' => 'Store Manager',
            ],
            [
                'username' => 'gakello',
                'full_name' => 'Grace Akello',
                'email' => 'gakello@uiri.go.ug',
                'phone' => '+256 700 000003',
                'branch_id' => 2, // UIRI Namanve
                'role' => 'Store Manager',
            ],
        ];

        foreach ($users as $user) {
            $role = Role::where('name', $user['role'])->where('guard_name', 'web')->firstOrFail();

            $createdUser = User::firstOrCreate(
                ['username' => $user['username']],
                [
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'password' => bcrypt('password'),
                    'phone' => $user['phone'],
                    'branch_id' => $user['branch_id'],
                    'role' => $user['role'],
                    'role_id' => $role->id,
                    'section_id' => null,
                    'department_id' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $createdUser->syncRoles([$user['role']]);
        }
    }
}
