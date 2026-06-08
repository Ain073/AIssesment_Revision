<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleAndSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin_dean',
            'department_chair',
            'instructor',
            'student',
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $superAdminRoleId = DB::table('roles')
            ->where('role_name', 'super_admin')
            ->value('role_id');

        DB::table('users')->updateOrInsert(
            ['email' => 'superadmin@psu.edu.ph'],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'middle_name' => null,
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $userId = DB::table('users')
            ->where('email', 'superadmin@psu.edu.ph')
            ->value('id');

        DB::table('user_roles')->updateOrInsert(
            [
                'user_id' => $userId,
                'role_id' => $superAdminRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
