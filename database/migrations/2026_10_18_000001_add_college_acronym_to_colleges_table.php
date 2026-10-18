<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colleges', function (Blueprint $table): void {
            $table->string('college_acronym', 50)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('colleges', function (Blueprint $table): void {
            $table->dropUnique(['college_acronym']);
            $table->dropColumn('college_acronym');
        });
    }
};
