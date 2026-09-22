<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $department = $this->scopedDepartment($this->currentUser());

        abort_if(! $department, 403, 'Department assignment is required before creating subjects.');

        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'subject_code')],
            'subject_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $subject = Subject::create([
            'department_id' => $department->department_id,
            'program_id' => null,
            'semester_id' => null,
            'subject_code' => $validated['subject_code'],
            'subject_name' => $validated['subject_name'],
            'year_level' => null,
            'is_active' => $validated['is_active'],
        ]);

        Log::info('Subject created by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'department_id' => $department->department_id,
        ]);

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse([], 'Subject saved successfully.');
        }

        return redirect()
            ->route('department-chair.subjects')
            ->with('status', 'Subject saved successfully.');
    }

    public function activateSemester(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->scopedDepartment($this->currentUser()), 403, 'Department assignment is required before managing subjects.');

        $validated = $request->validate([
            'semester_name' => ['required', 'string', Rule::in(Semester::names())],
        ]);

        $semester = Semester::activate($validated['semester_name']);

        Log::info('Active semester updated by department chair.', [
            'actor_id' => Auth::id(),
            'semester_id' => $semester->semester_id,
            'semester_name' => $semester->semester_name,
        ]);

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse([], 'Active semester updated successfully.');
        }

        return redirect()
            ->route('department-chair.subjects')
            ->with('status', 'Active semester updated successfully.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $department = $this->scopedDepartment($this->currentUser());
        $programs = $this->scopedPrograms($department);

        abort_if(! $department, 403, 'Department assignment is required before updating subjects.');
        if (! $this->subjectBelongsToDepartment($subject, $department->department_id, $programs)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This subject is no longer available under your department.',
                    'errors' => [
                        'subject' => ['This subject is no longer available under your department.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('department-chair.subjects')
                ->withErrors(['subject' => 'This subject is no longer available under your department.']);
        }

        $validated = $request->validate([
            'subject_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'subject_code')->ignore($subject->subject_id, 'subject_id'),
            ],
            'subject_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($subject, $department, $validated): void {
            $subject->update([
                'department_id' => $department->department_id,
                'program_id' => null,
                'semester_id' => null,
                'subject_code' => $validated['subject_code'],
                'subject_name' => $validated['subject_name'],
                'year_level' => null,
                'is_active' => $validated['is_active'],
            ]);
        });

        Log::info('Subject updated by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'department_id' => $department->department_id,
        ]);

        if ($request->expectsJson()) {
            return $this->subjectAjaxResponse([], 'Subject updated successfully.');
        }

        return redirect()
            ->route('department-chair.subjects')
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $department = $this->scopedDepartment($this->currentUser());
        $programs = $this->scopedPrograms($department);
        $subject->loadMissing('program', 'department');

        abort_if(! $department, 403, 'Department assignment is required before deleting subjects.');
        if (! $this->subjectBelongsToDepartment($subject, $department->department_id, $programs)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This subject is no longer available under your department.',
                    'errors' => [
                        'subject' => ['This subject is no longer available under your department.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('department-chair.subjects')
                ->withErrors(['subject' => 'This subject is no longer available under your department.']);
        }

        $legacyProgramId = $subject->program_id;
        $redirectFilters = [];

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
            'legacy_program_id' => $legacyProgramId,
            'department_id' => $department->department_id,
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
    private function subjects(?int $departmentId, Collection $programs): Collection
    {
        $programIds = $programs->pluck('program_id')->all();

        if (! $departmentId && empty($programIds)) {
            return collect();
        }

        return Subject::with(['department.college', 'program.department.college', 'semester'])
            ->where(function ($query) use ($departmentId, $programIds): void {
                if ($departmentId) {
                    $query->where('department_id', $departmentId);
                }

                if (! empty($programIds)) {
                    $method = $departmentId ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('program_id', $programIds);
                }
            })
            ->orderBy('subject_code')
            ->get();
    }

    private function subjectViewData(array $filters = []): array
    {
        $department = $this->scopedDepartment($this->currentUser());
        $programs = $this->scopedPrograms($department);
        $semesters = Semester::query()
            ->orderBy('semester_id')
            ->get();
        $activeSemester = $semesters->firstWhere('is_active', true);

        return [
            'programs' => $programs,
            'subjects' => $this->subjects($department?->department_id, $programs),
            'selectedProgramId' => null,
            'selectedProgramKey' => null,
            'selectedYearLevel' => null,
            'selectedSemester' => null,
            'hasSubjectFilters' => false,
            'activeSemester' => $activeSemester,
            'semesters' => $semesters,
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
                '[data-subjects-workspace]' => view('department-chair.subjects.partials.workspace', $data)->render(),
                '[data-subjects-modals]' => view('department-chair.subjects.partials.modals', $data)->render(),
            ],
        ]);
    }

    private function subjectBelongsToDepartment(Subject $subject, int $departmentId, Collection $programs): bool
    {
        if ((int) $subject->department_id === $departmentId) {
            return true;
        }

        return $programs
            ->pluck('program_id')
            ->contains((int) $subject->program_id);
    }
}
