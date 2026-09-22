<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'semester_id')) {
                $table->foreignId('semester_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('semesters', 'semester_id')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('subjects', 'semester_id')) {
            DB::statement(
                'UPDATE `classes`
                 INNER JOIN `class_details`
                    ON `class_details`.`class_id` = `classes`.`class_id`
                   AND `class_details`.`student_id` IS NULL
                 INNER JOIN `subjects`
                    ON `subjects`.`subject_id` = `class_details`.`subject_id`
                 SET `classes`.`semester_id` = COALESCE(`classes`.`semester_id`, `subjects`.`semester_id`)
                 WHERE `classes`.`semester_id` IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'semester_id')) {
                $table->dropConstrainedForeignId('semester_id');
            }
        });
    }
};
