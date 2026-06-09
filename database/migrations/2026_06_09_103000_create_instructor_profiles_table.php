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
        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id('instructor_profile_id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'department_id')
                ->nullOnDelete();
            $table->timestamps();
        });

        $timestamp = now();
        $rows = DB::table('users')
            ->select('id as user_id', 'department_id')
            ->whereNotNull('department_id')
            ->get()
            ->map(fn (object $row) => [
                'user_id' => $row->user_id,
                'department_id' => $row->department_id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('instructor_profiles')->insert($rows);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('status')
                ->constrained('departments', 'department_id')
                ->nullOnDelete();
        });

        $timestamp = now();
        $rows = DB::table('instructor_profiles')
            ->select('user_id', 'department_id')
            ->whereNotNull('department_id')
            ->get()
            ->all();

        foreach ($rows as $row) {
            DB::table('users')
                ->where('id', $row->user_id)
                ->update([
                    'department_id' => $row->department_id,
                    'updated_at' => $timestamp,
                ]);
        }

        Schema::dropIfExists('instructor_profiles');
    }
};
