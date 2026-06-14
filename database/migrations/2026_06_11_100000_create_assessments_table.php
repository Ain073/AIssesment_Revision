<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id('assessment_id');
            $table->foreignId('instructor_id')
                ->constrained('instructor_profiles', 'instructor_profile_id')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects', 'subject_id')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 50);
            $table->string('report_category', 100)->nullable();
            $table->string('reporting_term', 100)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->index(['instructor_id', 'subject_id'], 'assessments_instructor_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
