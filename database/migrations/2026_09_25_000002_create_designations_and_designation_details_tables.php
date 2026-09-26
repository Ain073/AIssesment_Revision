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
        Schema::create('designations', function (Blueprint $table) {
            $table->id('designation_id');
            $table->string('designation_name')->unique();
            $table->timestamps();
        });

        // Seed initial designations from the ERD
        $timestamp = now();
        DB::table('designations')->insert([
            [
                'designation_id' => 1,
                'designation_name' => 'Dean',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'designation_id' => 2,
                'designation_name' => 'Department Chair',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        Schema::create('designation_details', function (Blueprint $table) {
            $table->id('designation_details_id');
            $table->foreignId('instructor_id')
                ->constrained('instructor_profiles', 'instructor_profile_id')
                ->cascadeOnDelete();
            $table->foreignId('designation_id')
                ->constrained('designations', 'designation_id')
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'department_id')
                ->nullOnDelete();
            $table->foreignId('college_id')
                ->nullable()
                ->constrained('colleges', 'college_id')
                ->nullOnDelete();
            $table->string('academic_year')->default('2025-2026');
            $table->date('effectivity_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // 'active', 'completed', 'revoked'
            $table->foreignId('designated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
            $table->index(['designation_id', 'status']);
        });

        // Backfill current active designations from user_roles
        // 1. Backfill Dean
        $deanRole = DB::table('roles')->where('role_name', 'admin_dean')->first();
        if ($deanRole) {
            $deanUserRoles = DB::table('user_roles')->where('role_id', $deanRole->role_id)->get();
            foreach ($deanUserRoles as $ur) {
                $profile = DB::table('instructor_profiles')
                    ->join('departments', 'instructor_profiles.department_id', '=', 'departments.department_id')
                    ->where('instructor_profiles.user_id', $ur->user_id)
                    ->select('instructor_profiles.instructor_profile_id', 'departments.college_id', 'departments.department_id')
                    ->first();

                if ($profile) {
                    DB::table('designation_details')->insert([
                        'instructor_id' => $profile->instructor_profile_id,
                        'designation_id' => 1, // Dean
                        'college_id' => $profile->college_id,
                        'department_id' => $profile->department_id,
                        'academic_year' => '2025-2026',
                        'effectivity_date' => now()->toDateString(),
                        'end_date' => null,
                        'status' => 'active',
                        'remarks' => 'Initial system backfill from existing dean role',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        }

        // 2. Backfill Department Chair
        $chairRole = DB::table('roles')->where('role_name', 'department_chair')->first();
        if ($chairRole) {
            $chairUserRoles = DB::table('user_roles')->where('role_id', $chairRole->role_id)->get();
            foreach ($chairUserRoles as $ur) {
                $profile = DB::table('instructor_profiles')
                    ->join('departments', 'instructor_profiles.department_id', '=', 'departments.department_id')
                    ->where('instructor_profiles.user_id', $ur->user_id)
                    ->select('instructor_profiles.instructor_profile_id', 'departments.college_id', 'departments.department_id')
                    ->first();

                if ($profile) {
                    DB::table('designation_details')->insert([
                        'instructor_id' => $profile->instructor_profile_id,
                        'designation_id' => 2, // Department Chair
                        'college_id' => $profile->college_id,
                        'department_id' => $profile->department_id,
                        'academic_year' => '2025-2026',
                        'effectivity_date' => now()->toDateString(),
                        'end_date' => null,
                        'status' => 'active',
                        'remarks' => 'Initial system backfill from existing department chair role',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designation_details');
        Schema::dropIfExists('designations');
    }
};
