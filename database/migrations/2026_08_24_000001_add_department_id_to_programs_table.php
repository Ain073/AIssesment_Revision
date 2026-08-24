<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('programs', 'department_id')) {
            Schema::table('programs', function (Blueprint $table): void {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('college_id')
                    ->constrained('departments', 'department_id')
                    ->cascadeOnDelete();
            });

            $this->backfillProgramDepartments();
        }

        $this->dropCollegeProgramUniqueIndex();
        $this->addDepartmentProgramUniqueIndex();
    }

    public function down(): void
    {
        $this->dropDepartmentProgramUniqueIndex();
        $this->addCollegeProgramUniqueIndex();

        if (Schema::hasColumn('programs', 'department_id')) {
            Schema::table('programs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }

    private function backfillProgramDepartments(): void
    {
        $departmentsByCollege = DB::table('departments')
            ->select('department_id', 'college_id', 'dept_name')
            ->orderBy('department_id')
            ->get()
            ->groupBy('college_id');

        DB::table('programs')
            ->select('program_id', 'college_id', 'program_name')
            ->whereNull('department_id')
            ->orderBy('program_id')
            ->get()
            ->each(function ($program) use ($departmentsByCollege): void {
                $departmentId = $this->bestDepartmentIdForProgram(
                    (string) $program->program_name,
                    $departmentsByCollege->get($program->college_id, collect()),
                );

                if ($departmentId) {
                    DB::table('programs')
                        ->where('program_id', $program->program_id)
                        ->update(['department_id' => $departmentId]);
                }
            });
    }

    private function bestDepartmentIdForProgram(string $programName, $departments): ?int
    {
        if ($departments->isEmpty()) {
            return null;
        }

        $programWords = collect(preg_split('/[^a-z0-9]+/', Str::lower($programName)) ?: [])
            ->filter(fn (string $word): bool => strlen($word) >= 3)
            ->unique();

        $bestDepartment = $departments
            ->map(function ($department) use ($programWords) {
                $departmentWords = collect(preg_split('/[^a-z0-9]+/', Str::lower((string) $department->dept_name)) ?: [])
                    ->filter(fn (string $word): bool => strlen($word) >= 3)
                    ->unique();

                $department->match_score = $departmentWords->intersect($programWords)->count();

                return $department;
            })
            ->sortByDesc('match_score')
            ->first();

        return (int) ($bestDepartment?->department_id ?? $departments->first()->department_id);
    }

    private function dropCollegeProgramUniqueIndex(): void
    {
        if (! Schema::hasColumn('programs', 'college_id')) {
            return;
        }

        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->dropUnique('programs_college_id_program_name_unique');
            });
        } catch (Throwable) {
            // Index may already be absent on fresh databases.
        }
    }

    private function addCollegeProgramUniqueIndex(): void
    {
        if (! Schema::hasColumn('programs', 'college_id')) {
            return;
        }

        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->unique(['college_id', 'program_name']);
            });
        } catch (Throwable) {
            // Index may already exist.
        }
    }

    private function dropDepartmentProgramUniqueIndex(): void
    {
        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->dropUnique('programs_department_id_program_name_unique');
            });
        } catch (Throwable) {
            // Index may already be absent.
        }
    }

    private function addDepartmentProgramUniqueIndex(): void
    {
        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->unique(['department_id', 'program_name']);
            });
        } catch (Throwable) {
            // Index may already exist on fresh databases.
        }
    }
};
