<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameColumnIfPresent('submissions', 'class_assessment_id', 'publish_assessment_id', [
            'submissions_class_assessment_id_foreign',
            'assessment_attempts_class_assessment_id_foreign',
        ]);

        if (Schema::hasColumn('submissions', 'publish_assessment_id')) {
            $this->addIndexIfMissing('submissions', 'submissions_publish_assessment_lookup_index', ['publish_assessment_id']);
            $this->dropForeignKeyIfExists('submissions', 'submissions_publish_assessment_id_foreign');
            $this->dropIndexIfExists('submissions', 'assessment_attempt_unique');
            $this->addUniqueIfMissing(
                'submissions',
                'submissions_publish_assessment_student_attempt_unique',
                ['publish_assessment_id', 'student_profile_id', 'attempt_number']
            );
            $this->addPublishAssessmentForeignKeyIfMissing('submissions', 'submissions_publish_assessment_id_foreign', 'publish_assessment_id');
        }

        $this->renameColumnIfPresent('reports', 'class_assessment_id', 'publish_assessment_id', [
            'reports_class_assessment_id_foreign',
        ]);

        if (Schema::hasColumn('reports', 'publish_assessment_id')) {
            $this->addIndexIfMissing('reports', 'reports_publish_assessment_lookup_index', ['publish_assessment_id']);
            $this->dropForeignKeyIfExists('reports', 'reports_publish_assessment_id_foreign');
            $this->dropIndexIfExists('reports', 'reports_class_assessment_type_unique');
            $this->addUniqueIfMissing(
                'reports',
                'reports_publish_assessment_type_unique',
                ['publish_assessment_id', 'report_type']
            );
            $this->addPublishAssessmentForeignKeyIfMissing('reports', 'reports_publish_assessment_id_foreign', 'publish_assessment_id');
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('reports', 'reports_publish_assessment_id_foreign');
        $this->dropIndexIfExists('reports', 'reports_publish_assessment_type_unique');
        $this->dropIndexIfExists('reports', 'reports_publish_assessment_lookup_index');
        $this->renameColumnIfPresent('reports', 'publish_assessment_id', 'class_assessment_id', []);
        if (Schema::hasColumn('reports', 'class_assessment_id')) {
            $this->addUniqueIfMissing(
                'reports',
                'reports_class_assessment_type_unique',
                ['class_assessment_id', 'report_type']
            );
            $this->addPublishAssessmentForeignKeyIfMissing('reports', 'reports_class_assessment_id_foreign', 'class_assessment_id');
        }

        $this->dropForeignKeyIfExists('submissions', 'submissions_publish_assessment_id_foreign');
        $this->dropIndexIfExists('submissions', 'submissions_publish_assessment_student_attempt_unique');
        $this->dropIndexIfExists('submissions', 'submissions_publish_assessment_lookup_index');
        $this->renameColumnIfPresent('submissions', 'publish_assessment_id', 'class_assessment_id', []);
        if (Schema::hasColumn('submissions', 'class_assessment_id')) {
            $this->addUniqueIfMissing(
                'submissions',
                'assessment_attempt_unique',
                ['class_assessment_id', 'student_profile_id', 'attempt_number']
            );
            $this->addPublishAssessmentForeignKeyIfMissing('submissions', 'assessment_attempts_class_assessment_id_foreign', 'class_assessment_id');
        }
    }

    private function renameColumnIfPresent(
        string $table,
        string $from,
        string $to,
        array $foreignKeysToDrop
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from)) {
            return;
        }

        foreach ($foreignKeysToDrop as $name) {
            $this->dropForeignKeyIfExists($table, $name);
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }

    private function addPublishAssessmentForeignKeyIfMissing(string $table, string $foreignKey, string $column): void
    {
        if ($this->foreignKeyExists($table, $foreignKey)) {
            return;
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$foreignKey}` FOREIGN KEY (`{$column}`) REFERENCES `publish_assessment` (`publish_assessment_id`) ON DELETE CASCADE"
        );
    }

    private function addUniqueIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnsSql = collect($columns)
            ->map(fn (string $column): string => "`{$column}`")
            ->join(', ');

        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$index}` ({$columnsSql})");
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnsSql = collect($columns)
            ->map(fn (string $column): string => "`{$column}`")
            ->join(', ');

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnsSql})");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
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
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
