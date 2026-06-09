<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = now();

        $userIds = DB::table('user_roles')
            ->join('roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('roles.role_name', 'instructor')
            ->pluck('user_roles.user_id');

        foreach ($userIds as $userId) {
            DB::table('instructor_profiles')->updateOrInsert(
                ['user_id' => $userId],
                ['updated_at' => $timestamp, 'created_at' => $timestamp],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep instructor profile records because they may already contain
        // department assignments or additional profile data by this point.
    }
};
