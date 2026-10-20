<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->mediumText('course_code_title')->nullable()->change();
        });
    }

    public function down(): void
    {
        $length = DB::connection()->getDriverName() === 'sqlite' ? 'length' : 'char_length';
        if (DB::table('reports')->whereRaw("{$length}(course_code_title) > 255")->exists()) {
            throw new RuntimeException('Cannot restore the 255-character column while combined course titles exceed 255 characters.');
        }

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('course_code_title', 255)->nullable()->change();
        });
    }
};
