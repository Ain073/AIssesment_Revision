<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_item_choices', function (Blueprint $table) {
            $table->id('assessment_item_choice_id');
            $table->foreignId('assessment_item_id')
                ->constrained('assessment_items', 'assessment_item_id')
                ->cascadeOnDelete();
            $table->text('choice_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['assessment_item_id', 'sort_order'], 'assessment_item_choices_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_item_choices');
    }
};
