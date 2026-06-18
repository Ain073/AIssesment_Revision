<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_assessment', function (Blueprint $table) {
            $table->string('display_mode', 30)->default('all_questions')->after('warning_limit');
        });
    }

    public function down(): void
    {
        Schema::table('class_assessment', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
