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
            $table->string('status', 20)->default('approved');
            $table->string('entry_method', 30)->default('manual_add');
            $table->timestamps();

            $table->unique(
                ['class_id', 'student_id', 'subject_id'],
                'class_details_student_subject_unique'
            );
            $table->index(['instructor_id', 'subject_id'], 'class_details_instructor_subject_index');
            $table->index(['class_id', 'status'], 'class_details_class_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_details');
    }
};
