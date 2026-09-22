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
            if (! Schema::hasColumn('classes', 'program_id')) {
                $table->foreignId('program_id')
                    ->nullable()
                    ->after('class_id')
                    ->constrained('programs', 'program_id')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('subjects', 'program_id')) {
            DB::statement(
                'UPDATE `classes`
                 INNER JOIN `class_details`
                    ON `class_details`.`class_id` = `classes`.`class_id`
                   AND `class_details`.`student_id` IS NULL
                 INNER JOIN `subjects`
                    ON `subjects`.`subject_id` = `class_details`.`subject_id`
                 SET `classes`.`program_id` = COALESCE(`classes`.`program_id`, `subjects`.`program_id`)
                 WHERE `classes`.`program_id` IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'program_id')) {
                $table->dropConstrainedForeignId('program_id');
            }
        });
    }
};
