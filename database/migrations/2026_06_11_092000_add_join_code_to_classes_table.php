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
            $table->string('join_code', 12)->nullable()->unique()->after('join_token');
        });

        DB::table('classes')
            ->whereNull('join_code')
            ->orderBy('class_id')
            ->get(['class_id'])
            ->each(function (object $class): void {
                do {
                    $code = Str::upper(Str::random(6));
                } while (DB::table('classes')->where('join_code', $code)->exists());

                DB::table('classes')
                    ->where('class_id', $class->class_id)
                    ->update(['join_code' => $code]);
            });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
    }
};
