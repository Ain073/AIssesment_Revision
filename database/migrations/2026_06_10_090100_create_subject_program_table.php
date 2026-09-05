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
        Schema::create('subject_program', function (Blueprint $table) {
            $table->id('subject_program_id');
            $table->foreignId('subject_id')
                ->constrained('subjects', 'subject_id')
                ->cascadeOnDelete();
            $table->foreignId('program_id')
                ->constrained('programs', 'program_id')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->timestamps();

            $table->unique(['subject_id', 'program_id', 'year_level'], 'subject_program_scope_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_program');
    }
};
