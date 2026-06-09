<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['units', 'subject_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->first()
                ->constrained('departments', 'department_id')
                ->nullOnDelete();
            $table->decimal('units', 4, 1)->default(3.0)->after('subject_name');
            $table->string('subject_type', 20)->default('major')->after('units');
        });
    }
};
