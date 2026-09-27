<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill department_id from program if any subject is missing department_id
        if (Schema::hasColumn('subjects', 'department_id') && Schema::hasColumn('subjects', 'program_id')) {
            DB::statement(
                'UPDATE `subjects`
                 INNER JOIN `programs`
                    ON `programs`.`program_id` = `subjects`.`program_id`
                 SET `subjects`.`department_id` = COALESCE(`subjects`.`department_id`, `programs`.`department_id`)
                 WHERE `subjects`.`department_id` IS NULL'
            );
        }

        // Drop foreign keys and columns
        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'program_id')) {
                $table->dropConstrainedForeignId('program_id');
            }

            if (Schema::hasColumn('subjects', 'semester_id')) {
                $table->dropConstrainedForeignId('semester_id');
            }

            if (Schema::hasColumn('subjects', 'year_level')) {
                $table->dropColumn('year_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'program_id')) {
                $table->foreignId('program_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('programs', 'program_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('subjects', 'semester_id')) {
                $table->foreignId('semester_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('semesters', 'semester_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('subjects', 'year_level')) {
                $table->unsignedTinyInteger('year_level')
                    ->nullable()
                    ->after('semester_id');
            }
        });
    }
};
