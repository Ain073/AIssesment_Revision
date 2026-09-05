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
            if (! Schema::hasColumn('subjects', 'program_id')) {
                $table->foreignId('program_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('programs', 'program_id')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('subjects', 'year_level')) {
                $table->unsignedTinyInteger('year_level')
                    ->nullable()
                    ->after('semester_id');
            }
        });

        if (Schema::hasTable('subject_program')) {
            DB::statement(
                'UPDATE subjects
                 INNER JOIN (
                    SELECT subject_id, MIN(subject_program_id) AS first_mapping_id
                    FROM subject_program
                    GROUP BY subject_id
                 ) first_mapping ON first_mapping.subject_id = subjects.subject_id
                 INNER JOIN subject_program ON subject_program.subject_program_id = first_mapping.first_mapping_id
                 SET subjects.program_id = COALESCE(subjects.program_id, subject_program.program_id),
                     subjects.year_level = COALESCE(subjects.year_level, subject_program.year_level)'
            );

            Schema::dropIfExists('subject_program');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_program')) {
            Schema::create('subject_program', function (Blueprint $table): void {
                $table->id('subject_program_id');
                $table->foreignId('subject_id')
                    ->constrained('subjects', 'subject_id')
                    ->cascadeOnDelete();
                $table->foreignId('program_id')
                    ->constrained('programs', 'program_id')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('year_level');
                $table->timestamps();

                $table->unique(['subject_id', 'program_id', 'year_level'], 'subject_program_scope_unique');
            });
        }

        if (Schema::hasColumn('subjects', 'program_id') && Schema::hasColumn('subjects', 'year_level')) {
            DB::table('subjects')
                ->whereNotNull('program_id')
                ->whereNotNull('year_level')
                ->orderBy('subject_id')
                ->get(['subject_id', 'program_id', 'year_level'])
                ->each(function (object $subject): void {
                    DB::table('subject_program')->updateOrInsert(
                        [
                            'subject_id' => $subject->subject_id,
                            'program_id' => $subject->program_id,
                            'year_level' => $subject->year_level,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                });
        }

        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'program_id')) {
                $table->dropConstrainedForeignId('program_id');
            }

            if (Schema::hasColumn('subjects', 'year_level')) {
                $table->dropColumn('year_level');
            }
        });
    }
};
