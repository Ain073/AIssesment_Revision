<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id('student_profile_id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('program_id')
                ->nullable()
                ->constrained('programs', 'program_id')
                ->nullOnDelete();
            $table->string('student_number')->nullable()->unique();
            $table->timestamps();
        });

        $timestamp = now();

        $userIds = DB::table('user_roles')
            ->join('roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('roles.role_name', 'student')
            ->pluck('user_roles.user_id');

        foreach ($userIds as $userId) {
            DB::table('student_profiles')->updateOrInsert(
                ['user_id' => $userId],
                ['created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
