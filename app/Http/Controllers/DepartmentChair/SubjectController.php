<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
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
            'subject_code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'subject_code')],
            'subject_name' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in([1, 2, 3, 4])],
            'semester' => ['required', Rule::in(Semester::names())],
            'is_active' => ['required', 'boolean'],
        ]);
        $semester = $this->semesterByName($validated['semester']);

        $subject = Subject::create([
            'program_id' => $validated['program_id'],
            'semester_id' => $semester->semester_id,
            'subject_code' => $validated['subject_code'],
            'subject_name' => $validated['subject_name'],
            'year_level' => $validated['year_level'],
            'is_active' => $validated['is_active'],
        ]);

        Log::info('Subject created by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'program_id' => $validated['program_id'],
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ]);

        $filters = [
            'program' => Program::query()->whereKey($validated['program_id'])->value('public_id'),
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ];

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($filters, 'Subject saved successfully.');
        }

        return redirect()
            ->route('department-chair.subjects', $filters)
            ->with('status', 'Subject saved successfully.');
    }

    public function activateSemester(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->scopedDepartment($this->currentUser()), 403, 'Department assignment is required before managing subjects.');

        $validated = $request->validate([
            'semester' => ['required', Rule::in(Semester::names())],
        ]);

        Semester::activate($validated['semester']);

        Log::info('Active academic semester changed by department chair.', [
            'actor_id' => Auth::id(),
            'semester_name' => $validated['semester'],
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

    public function update(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $programs = $this->scopedPrograms($this->scopedDepartment($this->currentUser()));
        $programIds = $programs->pluck('program_id')->all();

        abort_if($programs->isEmpty(), 403, 'A department program is required before updating subjects.');
        abort_unless(in_array((int) $subject->program_id, $programIds, true), 404);

        $validated = $request->validate([
            'program_id' => ['required', 'integer', Rule::in($programIds)],
            'subject_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'subject_code')->ignore($subject->subject_id, 'subject_id'),
            ],
            'subject_name' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in([1, 2, 3, 4])],
            'semester' => ['required', Rule::in(Semester::names())],
            'is_active' => ['required', 'boolean'],
        ]);
        $semester = $this->semesterByName($validated['semester']);

        DB::transaction(function () use ($subject, $validated, $semester): void {
            $subject->update([
                'program_id' => $validated['program_id'],
                'semester_id' => $semester->semester_id,
                'subject_code' => $validated['subject_code'],
                'subject_name' => $validated['subject_name'],
                'year_level' => $validated['year_level'],
                'is_active' => $validated['is_active'],
            ]);
        });

        Log::info('Subject updated by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
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

    public function destroy(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $programs = $this->scopedPrograms($this->scopedDepartment($this->currentUser()));
        $programIds = $programs->pluck('program_id')->all();
        $subject->loadMissing('program');

        abort_if($programs->isEmpty(), 403, 'A department program is required before deleting subjects.');
        abort_unless(in_array((int) $subject->program_id, $programIds, true), 404);

        $program = $subject->program;
        $programId = $subject->program_id;
        $selectedProgram = $programs->firstWhere('public_id', $request->query('program'));
        $yearLevel = $request->integer('year_level');
        $semester = $request->query('semester');
        $redirectFilters = [
            'program' => $selectedProgram?->public_id ?? $program?->public_id,
            'year_level' => in_array($yearLevel, [1, 2, 3, 4], true) ? $yearLevel : null,
            'semester' => in_array($semester, Semester::names(), true) ? $semester : null,
        ];

        try {
            $subjectDeleted = DB::transaction(function () use ($subject): bool {
                if ($subject->classes()->exists()
                    || $subject->classDetails()->exists()
                    || $subject->assessments()->exists()) {
                    return false;
                }

                $subject->delete();

                return true;
            });
        } catch (Throwable $exception) {
            Log::warning('Subject delete failed.', [
                'actor_id' => Auth::id(),
                'subject_id' => $subject->subject_id,
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

        if (! $subjectDeleted) {
            $message = 'This subject is already linked to classes or assessments and cannot be deleted.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()
                ->route('department-chair.subjects', array_filter($redirectFilters))
                ->withErrors($message);
        }

        Log::info('Subject deleted by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'program_id' => $programId,
        ]);

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse($redirectFilters, 'Subject deleted successfully.');
        }

        return redirect()
            ->route('department-chair.subjects', array_filter($redirectFilters))
            ->with('status', 'Subject deleted successfully.');
    }

    /**
     * @param  Collection<int, Program>  $programs
     */
    private function subjects(Collection $programs, ?int $programId = null, ?int $yearLevel = null, ?string $semester = null)
    {
        $programIds = $programs->pluck('program_id')->all();

        return Subject::with(['program.department.college', 'semester'])
            ->whereIn('program_id', $programIds)
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->when($yearLevel, fn ($query) => $query->where('year_level', $yearLevel))
            ->when($semester, fn ($query) => $query->whereHas('semester', fn ($semesterQuery) => $semesterQuery->where('semester_name', $semester)))
            ->orderBy(
                Program::select('program_name')
                    ->whereColumn('programs.program_id', 'subjects.program_id')
            )
            ->orderBy('year_level')
            ->orderBy('semester_id')
            ->orderBy('subject_code')
            ->get();
    }

    private function semesterByName(string $semesterName): Semester
    {
        return Semester::query()->firstOrCreate(
            ['semester_name' => $semesterName],
            ['is_active' => false],
        );
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

        if (! in_array($selectedSemester, Semester::names(), true)) {
            $selectedSemester = null;
        }

        return [
            'programs' => $programs,
            'subjects' => $this->subjects($programs, $selectedProgramId, $selectedYearLevel, $selectedSemester),
            'selectedProgramId' => $selectedProgramId,
            'selectedProgramKey' => $selectedProgram?->public_id,
            'selectedYearLevel' => $selectedYearLevel,
            'selectedSemester' => $selectedSemester,
            'hasSubjectFilters' => $selectedProgramId || $selectedYearLevel || $selectedSemester,
            'activeSemester' => Semester::activeName(),
            'semesters' => Semester::names(),
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
