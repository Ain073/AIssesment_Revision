<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('join_token', 64)->nullable()->unique()->after('school_year');
        });

        DB::table('classes')
            ->whereNull('join_token')
            ->orderBy('class_id')
            ->get(['class_id'])
            ->each(function (object $class): void {
                DB::table('classes')
                    ->where('class_id', $class->class_id)
                    ->update(['join_token' => Str::random(40)]);
            });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['join_token']);
            $table->dropColumn('join_token');
        });
    }
};
