<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id('class_id');
            $table->foreignId('instructor_id')
                ->constrained('instructor_profiles', 'instructor_profile_id')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->string('section_name')->nullable();
            $table->string('class_name')->nullable();
            $table->string('school_year')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
