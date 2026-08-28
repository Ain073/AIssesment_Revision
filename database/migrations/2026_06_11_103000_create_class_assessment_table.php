<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publish_assessment', function (Blueprint $table) {
            $table->id('publish_assessment_id');
            $table->foreignId('assessment_id')
                ->constrained('assessments', 'assessment_id')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('class_details_id');
            $table->dateTime('available_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('publish_status', 30)->default('draft');
            $table->boolean('score_visibility')->default(false);
            $table->boolean('answer_visibility')->default(false);
            $table->unsignedSmallInteger('attempt_limit')->default(1);
            $table->boolean('shuffle_items')->default(false);
            $table->boolean('shuffle_choices')->default(false);
            $table->unsignedSmallInteger('warning_limit')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'class_details_id'], 'publish_assessment_unique');
            $table->index(['class_details_id', 'publish_status'], 'publish_assessment_detail_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_assessment');
    }
};
