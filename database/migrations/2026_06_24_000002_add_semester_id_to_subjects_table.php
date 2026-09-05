<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('semesters', 'semester_id')
                ->nullOnDelete();
        });

        $defaultSemesterId = DB::table('semesters')
            ->where('semester_name', 'First Semester')
            ->value('semester_id')
            ?? DB::table('semesters')->orderBy('semester_id')->value('semester_id');

        if (! $defaultSemesterId) {
            return;
        }

        $semesterIds = DB::table('semesters')->pluck('semester_id', 'semester_name');

        $subjectProgramHasSemester = Schema::hasColumn('subject_program', 'semester');

        DB::table('subjects')
            ->orderBy('subject_id')
            ->get(['subject_id'])
            ->each(function (object $subject) use ($defaultSemesterId, $semesterIds, $subjectProgramHasSemester): void {
                $semesterName = $subjectProgramHasSemester
                    ? DB::table('subject_program')
                        ->where('subject_id', $subject->subject_id)
                        ->orderBy('subject_program_id')
                        ->value('semester')
                    : null;

                DB::table('subjects')
                    ->where('subject_id', $subject->subject_id)
                    ->update([
                        'semester_id' => $semesterIds[$semesterName] ?? $defaultSemesterId,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });
    }
};
