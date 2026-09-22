<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('departments', 'department_id')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('subjects', 'program_id')) {
            DB::statement(
                'UPDATE `subjects`
                 INNER JOIN `programs`
                    ON `programs`.`program_id` = `subjects`.`program_id`
                 SET `subjects`.`department_id` = COALESCE(`subjects`.`department_id`, `programs`.`department_id`)
                 WHERE `subjects`.`department_id` IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });
    }
};
