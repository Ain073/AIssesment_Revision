<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_assessment', function (Blueprint $table) {
            $table->boolean('prevent_copy_paste')->default(false)->after('answer_visibility');
            $table->boolean('detect_tab_switch')->default(false)->after('prevent_copy_paste');
            $table->boolean('screenshot_protection')->default(false)->after('detect_tab_switch');
        });
    }

    public function down(): void
    {
        Schema::table('class_assessment', function (Blueprint $table) {
            $table->dropColumn([
                'prevent_copy_paste',
                'detect_tab_switch',
                'screenshot_protection',
            ]);
        });
    }
};
