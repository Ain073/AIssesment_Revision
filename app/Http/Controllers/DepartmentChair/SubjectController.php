<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\AcademicSetting;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SubjectController extends BaseController
{
    public function index(Request $request): View
    {
        $user = $this->currentUser();

        return view('department-chair.subjects.index', $this->sharedData($user, 'subjects') + $this->subjectViewData($request->query()));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $programs = $this->scopedPrograms($this->scopedDepartment($this->currentUser()));
        $programIds = $programs->pluck('program_id')->all();

        abort_if($programs->isEmpty(), 403, 'A department program is required before creating subjects.');

        $validated = $request->validate([
            'program_id' => ['required', 'integer', Rule::in($programIds)],
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

        Log::info('Subject mapped by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'program_id' => $validated['program_id'],
            'subject_program_id' => $mapping->subject_program_id,
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ]);

        $filters = [
            'program' => Program::query()->whereKey($validated['program_id'])->value('public_id'),
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ];

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($filters, 'Subject saved and mapped to the selected program.');
        }

        return redirect()
            ->route('department-chair.subjects', $filters)
            ->with('status', 'Subject saved and mapped to the selected program.');
    }

    public function activateSemester(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->scopedDepartment($this->currentUser()), 403, 'Department assignment is required before managing subjects.');

        $validated = $request->validate([
            'semester' => ['required', Rule::in(AcademicSetting::SEMESTERS)],
        ]);

        AcademicSetting::query()->updateOrCreate(
            ['id' => 1],
            ['active_semester' => $validated['semester']],
        );

        Log::info('Active academic semester changed by department chair.', [
            'actor_id' => Auth::id(),
            'active_semester' => $validated['semester'],
        ]);

        $filters = [
            'program' => $request->query('program'),
            'year_level' => $request->query('year_level'),
            'semester' => $validated['semester'],
        ];

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($filters, "{$validated['semester']} is now the active semester.");
        }

        return redirect()
            ->route('department-chair.subjects', array_filter($filters))
            ->with('status', "{$validated['semester']} is now the active semester.");
    }

    public function update(Request $request, SubjectProgram $subjectProgram): RedirectResponse|JsonResponse
    {
        $programs = $this->scopedPrograms($this->scopedDepartment($this->currentUser()));
        $programIds = $programs->pluck('program_id')->all();
        $subjectProgram->loadMissing('subject');
        $subject = $subjectProgram->subject;

        abort_if($programs->isEmpty(), 403, 'A department program is required before updating subjects.');
        abort_unless($subject && in_array((int) $subjectProgram->program_id, $programIds, true), 404);

        $validated = $request->validate([
            'program_id' => [
                'required',
                'integer',
                Rule::in($programIds),
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

        Log::info('Subject mapping updated by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_program_id' => $subjectProgram->subject_program_id,
            'program_id' => $validated['program_id'],
        ]);

        $filters = [
            'program' => Program::query()->whereKey($validated['program_id'])->value('public_id'),
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ];

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($filters, 'Subject updated successfully.');
        }

        return redirect()
            ->route('department-chair.subjects', $filters)
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Request $request, SubjectProgram $subjectProgram): RedirectResponse|JsonResponse
    {
        $programs = $this->scopedPrograms($this->scopedDepartment($this->currentUser()));
        $programIds = $programs->pluck('program_id')->all();
        $subjectProgram->loadMissing(['subject', 'program']);

        abort_if($programs->isEmpty(), 403, 'A department program is required before deleting subjects.');
        abort_unless(in_array((int) $subjectProgram->program_id, $programIds, true), 404);

        $subject = $subjectProgram->subject;
        $program = $subjectProgram->program;
        $programId = $subjectProgram->program_id;
        $selectedProgram = $programs->firstWhere('public_id', $request->query('program'));
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

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unable to delete the subject right now. Please try again.',
                ], 422);
            }

            return redirect()
                ->route('department-chair.subjects', array_filter($redirectFilters))
                ->withErrors('Unable to delete the subject right now. Please try again.');
        }

        Log::info('Subject mapping deleted by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject?->subject_id,
            'subject_program_id' => $subjectProgram->subject_program_id,
            'program_id' => $programId,
            'subject_deleted' => $subjectDeleted,
        ]);

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($redirectFilters, 'Subject removed from the selected program.');
        }

        return redirect()
            ->route('department-chair.subjects', array_filter($redirectFilters))
            ->with('status', 'Subject removed from the selected program.');
    }

    /**
     * @param  Collection<int, Program>  $programs
     */
    private function subjectMappings(Collection $programs, ?int $programId = null, ?int $yearLevel = null, ?string $semester = null)
    {
        $programIds = $programs->pluck('program_id')->all();

        return SubjectProgram::with(['program.department.college', 'subject'])
            ->whereIn('program_id', $programIds)
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

    private function subjectViewData(array $filters = []): array
    {
        $department = $this->scopedDepartment($this->currentUser());
        $programs = $this->scopedPrograms($department);
        $selectedProgram = $programs->firstWhere('public_id', $filters['program'] ?? null);
        $selectedProgramId = $selectedProgram?->program_id;
        $selectedYearLevel = filter_var($filters['year_level'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $selectedSemester = $filters['semester'] ?? null;

        if (! in_array($selectedYearLevel, [1, 2, 3, 4], true)) {
            $selectedYearLevel = null;
        }

        if (! in_array($selectedSemester, AcademicSetting::SEMESTERS, true)) {
            $selectedSemester = null;
        }

        return [
            'programs' => $programs,
            'subjectMappings' => $this->subjectMappings($programs, $selectedProgramId, $selectedYearLevel, $selectedSemester),
            'selectedProgramId' => $selectedProgramId,
            'selectedProgramKey' => $selectedProgram?->public_id,
            'selectedYearLevel' => $selectedYearLevel,
            'selectedSemester' => $selectedSemester,
            'hasSubjectFilters' => $selectedProgramId || $selectedYearLevel || $selectedSemester,
            'activeSemester' => AcademicSetting::query()->value('active_semester'),
            'scopedDepartment' => $department,
            'subjectRoutePrefix' => 'department-chair',
        ];
    }

    private function subjectAjaxResponse(array $filters, string $message): JsonResponse
    {
        $filters = array_filter($filters);
        $data = $this->subjectViewData($filters);

        return response()->json([
            'message' => $message,
            'url' => route('department-chair.subjects', $filters),
            'fragments' => [
                '[data-subjects-term-toolbar]' => view('department-chair.subjects.partials.term-toolbar', $data)->render(),
                '[data-subjects-workspace]' => view('department-chair.subjects.partials.workspace', $data)->render(),
                '[data-subjects-modals]' => view('department-chair.subjects.partials.modals', $data)->render(),
            ],
        ]);
    }
}
