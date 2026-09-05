<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleAndSuperAdminSeeder extends Seeder
{
    private string $adminEmail = 'admin@psu.edu.ph';

    private string $legacyAdminEmail = 'superadmin@psu.edu.ph';

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

        $admin = DB::table('users')
            ->whereIn('email', [$this->adminEmail, $this->legacyAdminEmail])
            ->orderByRaw('email = ? DESC', [$this->adminEmail])
            ->first();

        if ($admin) {
            $userId = $admin->id;

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'name' => 'Admin',
                    'first_name' => 'Admin',
                    'middle_name' => null,
                    'last_name' => null,
                    'email' => $this->adminEmail,
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
        } else {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Admin',
                'first_name' => 'Admin',
                'middle_name' => null,
                'last_name' => null,
                'email' => $this->adminEmail,
                'password' => Hash::make('password'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
