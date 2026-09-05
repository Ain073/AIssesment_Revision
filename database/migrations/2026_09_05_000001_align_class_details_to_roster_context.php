<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('class_details', 'status')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->string('status', 20)->default('approved')->after('subject_id');
            });
        }

        if (! Schema::hasColumn('class_details', 'entry_method')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->string('entry_method', 30)->default('manual_add')->after('status');
            });
        }

        DB::table('class_details')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'approved']);

        DB::table('class_details')
            ->whereNull('entry_method')
            ->orWhere('entry_method', '')
            ->update(['entry_method' => 'manual_add']);

        if (! $this->indexExists('class_details', 'class_details_student_subject_unique')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->unique(['class_id', 'student_id', 'subject_id'], 'class_details_student_subject_unique');
            });
        }

        if (! $this->indexExists('class_details', 'class_details_instructor_subject_index')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->index(['instructor_id', 'subject_id'], 'class_details_instructor_subject_index');
            });
        }

        if (! $this->indexExists('class_details', 'class_details_class_status_index')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->index(['class_id', 'status'], 'class_details_class_status_index');
            });
        }

        if (Schema::hasColumn('class_details', 'semester_id')) {
            $this->dropForeignKeyIfExists('class_details', 'class_details_semester_id_foreign');
            $this->dropIndexIfExists('class_details', 'class_details_student_subject_semester_unique');
            $this->dropIndexIfExists('class_details', 'class_details_instructor_subject_semester_index');
            $this->dropIndexIfExists('class_details', 'class_details_class_semester_index');

            Schema::table('class_details', function (Blueprint $table): void {
                $table->dropColumn('semester_id');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('class_details', 'class_details_student_subject_unique');
        $this->dropIndexIfExists('class_details', 'class_details_instructor_subject_index');
        $this->dropIndexIfExists('class_details', 'class_details_class_status_index');

        if (! Schema::hasColumn('class_details', 'semester_id')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->foreignId('semester_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('semesters', 'semester_id')
                    ->nullOnDelete();
            });
        }

        if (! $this->indexExists('class_details', 'class_details_student_subject_semester_unique')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->unique(
                    ['class_id', 'student_id', 'subject_id', 'semester_id'],
                    'class_details_student_subject_semester_unique'
                );
            });
        }

        if (! $this->indexExists('class_details', 'class_details_instructor_subject_semester_index')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->index(['instructor_id', 'subject_id', 'semester_id'], 'class_details_instructor_subject_semester_index');
            });
        }

        if (! $this->indexExists('class_details', 'class_details_class_semester_index')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->index(['class_id', 'semester_id'], 'class_details_class_semester_index');
            });
        }

        if (Schema::hasColumn('class_details', 'entry_method')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->dropColumn('entry_method');
            });
        }

        if (Schema::hasColumn('class_details', 'status')) {
            Schema::table('class_details', function (Blueprint $table): void {
                $table->dropColumn('status');
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

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->exists();

        if (! $exists) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
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
