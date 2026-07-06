<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSetting;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $selectedProgram = Program::query()
            ->where('public_id', $request->query('program'))
            ->first();
        $selectedProgramId = $selectedProgram?->program_id;
        $selectedYearLevel = $request->integer('year_level');
        $selectedSemester = $request->query('semester');

        if (! in_array($selectedYearLevel, [1, 2, 3, 4], true)) {
            $selectedYearLevel = null;
        }

        if (! in_array($selectedSemester, AcademicSetting::SEMESTERS, true)) {
            $selectedSemester = null;
        }

        return view('super-admin.subjects.index', [
            'programs' => $this->programsList(),
            'subjectMappings' => $this->subjectMappings($selectedProgramId, $selectedYearLevel, $selectedSemester),
            'selectedProgramId' => $selectedProgramId,
            'selectedProgramKey' => $selectedProgram?->public_id,
            'selectedYearLevel' => $selectedYearLevel,
            'selectedSemester' => $selectedSemester,
            'hasSubjectFilters' => $selectedProgramId || $selectedYearLevel || $selectedSemester,
            'activeSemester' => AcademicSetting::query()->value('active_semester'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,program_id'],
            'subject_code' => ['required', 'string', 'max:255'],
            'subject_name' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in([1, 2, 3, 4])],
            'semester' => ['required', Rule::in(AcademicSetting::SEMESTERS)],
            'is_active' => ['required', 'boolean'],
        ]);

        $subject = Subject::updateOrCreate(
            ['subject_code' => $validated['subject_code']],
            [
                'subject_name' => $validated['subject_name'],
                'is_active' => $validated['is_active'],
            ],
        );

        $mapping = SubjectProgram::firstOrCreate([
            'subject_id' => $subject->subject_id,
            'program_id' => $validated['program_id'],
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ]);

        Log::info('Subject mapped by super admin.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'program_id' => $validated['program_id'],
            'subject_program_id' => $mapping->subject_program_id,
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ]);

        $programKey = Program::query()
            ->whereKey($validated['program_id'])
            ->value('public_id');

        return redirect()
            ->route('super-admin.subjects', [
                'program' => $programKey,
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
            ])
            ->with('status', 'Subject saved and mapped to the selected program.');
    }

    public function activateSemester(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester' => ['required', Rule::in(AcademicSetting::SEMESTERS)],
        ]);

        AcademicSetting::query()->updateOrCreate(
            ['id' => 1],
            ['active_semester' => $validated['semester']],
        );

        Log::info('Active academic semester changed by super admin.', [
            'actor_id' => Auth::id(),
            'active_semester' => $validated['semester'],
        ]);

        return redirect()
            ->route('super-admin.subjects', ['semester' => $validated['semester']])
            ->with('status', "{$validated['semester']} is now the active semester.");
    }

    public function update(Request $request, SubjectProgram $subjectProgram): RedirectResponse
    {
        $subjectProgram->loadMissing('subject');
        $subject = $subjectProgram->subject;

        abort_unless($subject, 404);

        $validated = $request->validate([
            'program_id' => [
                'required',
                'exists:programs,program_id',
                Rule::unique('subject_program', 'program_id')
                    ->where(fn ($query) => $query
                        ->where('subject_id', $subject->subject_id)
                        ->where('year_level', $request->input('year_level'))
                        ->where('semester', $request->input('semester')))
                    ->ignore($subjectProgram->subject_program_id, 'subject_program_id'),
            ],
            'subject_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'subject_code')->ignore($subject->subject_id, 'subject_id'),
            ],
            'subject_name' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in([1, 2, 3, 4])],
            'semester' => ['required', Rule::in(AcademicSetting::SEMESTERS)],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($subject, $subjectProgram, $validated): void {
            $subject->update([
                'subject_code' => $validated['subject_code'],
                'subject_name' => $validated['subject_name'],
                'is_active' => $validated['is_active'],
            ]);

            $subjectProgram->update([
                'program_id' => $validated['program_id'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
            ]);
        });

        Log::info('Subject mapping updated by super admin.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_program_id' => $subjectProgram->subject_program_id,
            'program_id' => $validated['program_id'],
        ]);

        $programKey = Program::query()
            ->whereKey($validated['program_id'])
            ->value('public_id');

        return redirect()
            ->route('super-admin.subjects', [
                'program' => $programKey,
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
            ])
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Request $request, SubjectProgram $subjectProgram): RedirectResponse
    {
        $subjectProgram->loadMissing(['subject', 'program']);
        $subject = $subjectProgram->subject;
        $program = $subjectProgram->program;
        $programId = $subjectProgram->program_id;
        $selectedProgram = Program::query()
            ->where('public_id', $request->query('program'))
            ->first();
        $yearLevel = $request->integer('year_level');
        $semester = $request->query('semester');
        $redirectFilters = [
            'program' => $selectedProgram?->public_id ?? $program?->public_id,
            'year_level' => in_array($yearLevel, [1, 2, 3, 4], true) ? $yearLevel : null,
            'semester' => in_array($semester, AcademicSetting::SEMESTERS, true) ? $semester : null,
        ];

        try {
            $subjectDeleted = DB::transaction(function () use ($subjectProgram, $subject): bool {
                $subjectProgram->delete();

                if (! $subject
                    || $subject->subjectPrograms()->exists()
                    || $subject->classes()->exists()
                    || $subject->assessments()->exists()) {
                    return false;
                }

                $subject->delete();

                return true;
            });
        } catch (Throwable $exception) {
            Log::warning('Subject mapping delete failed.', [
                'actor_id' => Auth::id(),
                'subject_program_id' => $subjectProgram->subject_program_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('super-admin.subjects', array_filter($redirectFilters))
                ->withErrors('Unable to delete the subject right now. Please try again.');
        }

        Log::info('Subject mapping deleted by super admin.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject?->subject_id,
            'subject_program_id' => $subjectProgram->subject_program_id,
            'program_id' => $programId,
            'subject_deleted' => $subjectDeleted,
        ]);

        return redirect()
            ->route('super-admin.subjects', array_filter($redirectFilters))
            ->with('status', 'Subject removed from the selected program.');
    }

    private function programsList()
    {
        return Program::with('college')
            ->withCount(['studentProfiles', 'subjectPrograms'])
            ->orderBy('program_name')
            ->get();
    }

    private function subjectMappings(?int $programId = null, ?int $yearLevel = null, ?string $semester = null)
    {
        return SubjectProgram::with(['program.college', 'subject'])
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->when($yearLevel, fn ($query) => $query->where('year_level', $yearLevel))
            ->when($semester, fn ($query) => $query->where('semester', $semester))
            ->orderBy(
                Program::select('program_name')
                    ->whereColumn('programs.program_id', 'subject_program.program_id')
            )
            ->orderBy('year_level')
            ->orderBy('semester')
            ->orderBy(
                Subject::select('subject_code')
                    ->whereColumn('subjects.subject_id', 'subject_program.subject_id')
            )
            ->get();
    }
}
