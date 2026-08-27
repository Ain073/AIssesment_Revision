<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_details', function (Blueprint $table) {
            $table->id('class_details_id');
            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnDelete();
            $table->foreignId('instructor_id')
                ->constrained('instructor_profiles', 'instructor_profile_id')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('student_profiles', 'student_profile_id')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects', 'subject_id')
                ->cascadeOnDelete();
            $table->foreignId('semester_id')
                ->constrained('semesters', 'semester_id')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['class_id', 'student_id', 'subject_id', 'semester_id'],
                'class_details_student_subject_semester_unique'
            );
            $table->index(['instructor_id', 'subject_id', 'semester_id'], 'class_details_instructor_subject_semester_index');
            $table->index(['class_id', 'semester_id'], 'class_details_class_semester_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_details');
    }
};
