<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passing_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('year_level')->unique();
            $table->decimal('passing_rate', 5, 2)->default(50);
            $table->timestamps();
        });

        $now = now();

        DB::table('passing_rate_settings')->insert([
            ['year_level' => 1, 'passing_rate' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['year_level' => 2, 'passing_rate' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['year_level' => 3, 'passing_rate' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['year_level' => 4, 'passing_rate' => 50, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('passing_rate_settings');
    }
};
