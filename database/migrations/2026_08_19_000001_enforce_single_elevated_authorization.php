<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminDeanRoleId = DB::table('roles')->where('role_name', 'admin_dean')->value('role_id');
        $departmentChairRoleId = DB::table('roles')->where('role_name', 'department_chair')->value('role_id');

        if (! $adminDeanRoleId || ! $departmentChairRoleId) {
            return;
        }

        $departmentChairUserIds = DB::table('user_roles')
            ->where('role_id', $departmentChairRoleId)
            ->pluck('user_id');

        if ($departmentChairUserIds->isEmpty()) {
            return;
        }

        DB::table('user_roles')
            ->where('role_id', $adminDeanRoleId)
            ->whereIn('user_id', $departmentChairUserIds)
            ->delete();
    }

    public function down(): void
    {
        // This cleanup removes ambiguous duplicate authorization and cannot be restored safely.
    }
};
