<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('class_details', 'student_id')) {
            $this->dropForeignKeyIfExists('class_details', 'class_details_student_id_foreign');
            DB::statement('ALTER TABLE `class_details` MODIFY `student_id` BIGINT UNSIGNED NULL');
            $this->addForeignKeyIfMissing(
                'class_details',
                'class_details_student_id_foreign',
                'student_id',
                'student_profiles',
                'student_profile_id',
                'CASCADE'
            );
        }

        if (Schema::hasColumn('classes', 'instructor_id') && Schema::hasColumn('classes', 'subject_id')) {
            DB::table('classes')
                ->whereNotNull('instructor_id')
                ->whereNotNull('subject_id')
                ->orderBy('class_id')
                ->get(['class_id', 'instructor_id', 'subject_id'])
                ->each(function (object $class): void {
                    DB::table('class_details')->updateOrInsert(
                        [
                            'class_id' => $class->class_id,
                            'student_id' => null,
                        ],
                        [
                            'instructor_id' => $class->instructor_id,
                            'subject_id' => $class->subject_id,
                            'status' => 'approved',
                            'entry_method' => 'class_setup',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                });

            DB::statement(
                'UPDATE `publish_assessment` AS `publish_assessment`
                 INNER JOIN `class_details` AS `published_detail`
                    ON `published_detail`.`class_details_id` = `publish_assessment`.`class_details_id`
                 INNER JOIN `class_details` AS `context_detail`
                    ON `context_detail`.`class_id` = `published_detail`.`class_id`
                   AND `context_detail`.`instructor_id` = `published_detail`.`instructor_id`
                   AND `context_detail`.`subject_id` = `published_detail`.`subject_id`
                   AND `context_detail`.`student_id` IS NULL
                 SET `publish_assessment`.`class_details_id` = `context_detail`.`class_details_id`'
            );
        }

        if (Schema::hasColumn('classes', 'subject_id')) {
            $this->dropForeignKeyIfExists('classes', 'classes_subject_id_foreign');
        }

        if (Schema::hasColumn('classes', 'instructor_id')) {
            $this->dropForeignKeyIfExists('classes', 'classes_instructor_id_foreign');
        }

        Schema::table('classes', function (Blueprint $table): void {
            foreach (['instructor_id', 'subject_id', 'class_name', 'school_year'] as $column) {
                if (Schema::hasColumn('classes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'instructor_id')) {
                $table->foreignId('instructor_id')
                    ->nullable()
                    ->after('class_id')
                    ->constrained('instructor_profiles', 'instructor_profile_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('classes', 'subject_id')) {
                $table->foreignId('subject_id')
                    ->nullable()
                    ->after('instructor_id')
                    ->constrained('subjects', 'subject_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('classes', 'class_name')) {
                $table->string('class_name')->nullable()->after('section_name');
            }

            if (! Schema::hasColumn('classes', 'school_year')) {
                $table->string('school_year')->nullable()->after('class_name');
            }
        });

        DB::statement(
            'UPDATE `classes`
             INNER JOIN `class_details`
                ON `class_details`.`class_id` = `classes`.`class_id`
               AND `class_details`.`student_id` IS NULL
             SET `classes`.`instructor_id` = `class_details`.`instructor_id`,
                 `classes`.`subject_id` = `class_details`.`subject_id`,
                 `classes`.`class_name` = CONCAT("Year ", `classes`.`year_level`, " - ", `classes`.`section_name`)'
        );

        if (Schema::hasColumn('class_details', 'student_id')) {
            $this->dropForeignKeyIfExists('class_details', 'class_details_student_id_foreign');
            DB::table('class_details')->whereNull('student_id')->delete();
            DB::statement('ALTER TABLE `class_details` MODIFY `student_id` BIGINT UNSIGNED NOT NULL');
            $this->addForeignKeyIfMissing(
                'class_details',
                'class_details_student_id_foreign',
                'student_id',
                'student_profiles',
                'student_profile_id',
                'CASCADE'
            );
        }
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $foreignKey,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete
    ): void {
        if ($this->foreignKeyExists($table, $foreignKey)) {
            return;
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$foreignKey}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`) ON DELETE {$onDelete}"
        );
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        if (! $this->foreignKeyExists($table, $foreignKey)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->exists();
    }
};
