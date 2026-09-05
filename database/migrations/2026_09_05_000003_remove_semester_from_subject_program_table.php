<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subject_program')) {
            return;
        }

        $this->addForeignKeySupportIndexes();
        $this->dropIndexIfExists('subject_program', 'subject_program_scope_unique');

        DB::statement(
            'DELETE duplicate_mapping FROM subject_program duplicate_mapping
             INNER JOIN subject_program kept_mapping
                ON kept_mapping.subject_id = duplicate_mapping.subject_id
               AND kept_mapping.program_id = duplicate_mapping.program_id
               AND kept_mapping.year_level = duplicate_mapping.year_level
               AND kept_mapping.subject_program_id < duplicate_mapping.subject_program_id'
        );

        if (Schema::hasColumn('subject_program', 'semester')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->dropColumn('semester');
            });
        }

        if (! $this->indexExists('subject_program', 'subject_program_scope_unique')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->unique(['subject_id', 'program_id', 'year_level'], 'subject_program_scope_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_program')) {
            return;
        }

        $this->addForeignKeySupportIndexes();
        $this->dropIndexIfExists('subject_program', 'subject_program_scope_unique');

        if (! Schema::hasColumn('subject_program', 'semester')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->string('semester', 50)->default('First Semester')->after('year_level');
            });
        }

        if (! $this->indexExists('subject_program', 'subject_program_scope_unique')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->unique(['subject_id', 'program_id', 'year_level', 'semester'], 'subject_program_scope_unique');
            });
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function addForeignKeySupportIndexes(): void
    {
        if (! $this->indexExists('subject_program', 'subject_program_subject_id_index')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->index('subject_id', 'subject_program_subject_id_index');
            });
        }

        if (! $this->indexExists('subject_program', 'subject_program_program_id_index')) {
            Schema::table('subject_program', function (Blueprint $table): void {
                $table->index('program_id', 'subject_program_program_id_index');
            });
        }
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
