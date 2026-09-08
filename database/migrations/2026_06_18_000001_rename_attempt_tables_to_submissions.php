<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assessment_answers') && $this->foreignKeyExists('assessment_answers', 'assessment_answers_assessment_attempt_id_foreign')) {
            Schema::table('assessment_answers', function (Blueprint $table) {
                $table->dropForeign(['assessment_attempt_id']);
            });
        }

        if (Schema::hasTable('assessment_answers') && $this->indexExists('assessment_answers', 'assessment_answer_unique')) {
            Schema::table('assessment_answers', function (Blueprint $table) {
                $table->dropUnique('assessment_answer_unique');
            });
        }

        if (Schema::hasTable('assessment_attempts') && ! Schema::hasTable('submissions')) {
            Schema::rename('assessment_attempts', 'submissions');
        }

        if (Schema::hasTable('assessment_answers') && ! Schema::hasTable('submission_answers')) {
            Schema::rename('assessment_answers', 'submission_answers');
        }

        if (Schema::hasColumn('submissions', 'assessment_attempt_id')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->renameColumn('assessment_attempt_id', 'submission_id');
            });
        }

        if (Schema::hasColumn('submissions', 'warnings_used')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->renameColumn('warnings_used', 'warning_count');
            });
        }

        if (Schema::hasColumn('submission_answers', 'assessment_answer_id')) {
            Schema::table('submission_answers', function (Blueprint $table) {
                $table->renameColumn('assessment_answer_id', 'submission_answer_id');
            });
        }

        if (Schema::hasColumn('submission_answers', 'assessment_attempt_id')) {
            Schema::table('submission_answers', function (Blueprint $table) {
                $table->renameColumn('assessment_attempt_id', 'submission_id');
            });
        }

        Schema::table('submission_answers', function (Blueprint $table) {
            if (! $this->foreignKeyExists('submission_answers', 'submission_answers_submission_id_foreign')) {
                $table->foreign('submission_id')
                    ->references('submission_id')
                    ->on('submissions')
                    ->cascadeOnDelete();
            }

            if (! $this->indexExists('submission_answers', 'submission_answer_unique')) {
                $table->unique(['submission_id', 'assessment_item_id'], 'submission_answer_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('submission_answers', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
            $table->dropUnique('submission_answer_unique');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->renameColumn('submission_id', 'assessment_attempt_id');
            $table->renameColumn('warning_count', 'warnings_used');
        });

        Schema::table('submission_answers', function (Blueprint $table) {
            $table->renameColumn('submission_answer_id', 'assessment_answer_id');
            $table->renameColumn('submission_id', 'assessment_attempt_id');
        });

        Schema::rename('submissions', 'assessment_attempts');
        Schema::rename('submission_answers', 'assessment_answers');

        Schema::table('assessment_answers', function (Blueprint $table) {
            $table->foreign('assessment_attempt_id')
                ->references('assessment_attempt_id')
                ->on('assessment_attempts')
                ->cascadeOnDelete();
            $table->unique(['assessment_attempt_id', 'assessment_item_id'], 'assessment_answer_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row): bool => ($row->name ?? null) === $index);
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('{$table}')"));

            return match ($constraint) {
                'assessment_answers_assessment_attempt_id_foreign' => $foreignKeys
                    ->contains(fn ($row): bool => ($row->from ?? null) === 'assessment_attempt_id'),
                'submission_answers_submission_id_foreign' => $foreignKeys
                    ->contains(fn ($row): bool => ($row->from ?? null) === 'submission_id'),
                default => false,
            };
        }

        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

};
