<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            if (! Schema::hasColumn('publish_assessment', 'original_due_at')) {
                $table->dateTime('original_due_at')->nullable()->after('due_at');
            }

            if (! Schema::hasColumn('publish_assessment', 'reopened_at')) {
                $table->dateTime('reopened_at')->nullable()->after('original_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_assessment', function (Blueprint $table) {
            if (Schema::hasColumn('publish_assessment', 'reopened_at')) {
                $table->dropColumn('reopened_at');
            }

            if (Schema::hasColumn('publish_assessment', 'original_due_at')) {
                $table->dropColumn('original_due_at');
            }
        });
    }
};
