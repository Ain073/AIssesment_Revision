<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id('assessment_answer_id');
            $table->foreignId('assessment_attempt_id')
                ->constrained('assessment_attempts', 'assessment_attempt_id')
                ->cascadeOnDelete();
            $table->foreignId('assessment_item_id')
                ->constrained('assessment_items', 'assessment_item_id')
                ->cascadeOnDelete();
            $table->foreignId('assessment_item_choice_id')
                ->nullable()
                ->constrained('assessment_item_choices', 'assessment_item_choice_id')
                ->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->timestamps();

            $table->unique(['assessment_attempt_id', 'assessment_item_id'], 'assessment_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
