<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('class_assessment_id')
                ->constrained('publish_assessment', 'publish_assessment_id')
                ->cascadeOnDelete();
            $table->string('report_type', 30);
            $table->text('ai_most_learned_draft')->nullable();
            $table->text('ai_least_learned_draft')->nullable();
            $table->text('concept_most_learned_skills')->nullable();
            $table->text('concept_least_learned_skills')->nullable();
            $table->text('issues_concern')->nullable();
            $table->text('interventions_done')->nullable();
            $table->text('future_plans_curriculum')->nullable();
            $table->string('report_status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['class_assessment_id', 'report_type'], 'reports_class_assessment_type_unique');
            $table->index(['report_type', 'report_status'], 'reports_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
