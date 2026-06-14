<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_items', function (Blueprint $table) {
            $table->id('assessment_item_id');
            $table->foreignId('assessment_id')
                ->constrained('assessments', 'assessment_id')
                ->cascadeOnDelete();
            $table->text('question_text');
            $table->string('item_type', 50);
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['assessment_id', 'sort_order'], 'assessment_items_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_items');
    }
};
