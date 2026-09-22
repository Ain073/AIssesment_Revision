<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            $table->unsignedSmallInteger('question_time_limit_seconds')
                ->nullable()
                ->after('display_mode');
        });
    }

    public function down(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            $table->dropColumn('question_time_limit_seconds');
        });
    }
};
