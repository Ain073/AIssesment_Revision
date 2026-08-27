<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id('semester_id');
            $table->string('semester_name', 50)->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('semesters')->insert([
            ['semester_name' => 'First Semester', 'is_active' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['semester_name' => 'Second Semester', 'is_active' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['semester_name' => 'Summer', 'is_active' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
