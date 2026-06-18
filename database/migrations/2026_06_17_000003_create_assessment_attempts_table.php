<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id('assessment_attempt_id');
            $table->foreignId('class_assessment_id')
                ->constrained('class_assessment', 'class_assessment_id')
                ->cascadeOnDelete();
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles', 'student_profile_id')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 30)->default('submitted');
            $table->unsignedSmallInteger('warnings_used')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['class_assessment_id', 'student_profile_id', 'attempt_number'], 'assessment_attempt_unique');
            $table->index(['student_profile_id', 'status'], 'assessment_attempt_student_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
