<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_items', function (Blueprint $table) {
            // Used by enumeration item type: null = not applicable, true = order matters, false = any order
            $table->boolean('order_sensitive')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_items', function (Blueprint $table) {
            $table->dropColumn('order_sensitive');
        });
    }
};
