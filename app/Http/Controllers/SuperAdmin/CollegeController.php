<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSetting;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollegeController extends Controller
{
    public function dashboard(): View
    {
        return view('super-admin.dashboard', [
            'totalColleges' => College::count(),
            'totalDepartments' => Department::count(),
            'totalAdminDeans' => $this->countUsersByRole('admin_dean'),
            'totalDepartmentChairs' => $this->countUsersByRole('department_chair'),
        ]);
    }

    public function index(): View
    {
        return view('super-admin.colleges', [
            'colleges' => $this->colleges(),
            'departments' => $this->departments(),
            'totalColleges' => College::count(),
            'totalDepartments' => Department::count(),
        ]);
    }

    public function programs(): View
    {
        return view('super-admin.programs', [
            'colleges' => College::query()
                ->orderBy('college_name')
                ->get(),
            'programs' => $this->programsList(),
            'totalPrograms' => Program::count(),
            'activePrograms' => Program::query()->where('is_active', true)->count(),
        ]);
    }

    public function subjects(Request $request): View
    {
        $selectedProgramId = $request->integer('program');
        $selectedYearLevel = $request->integer('year_level');
        $selectedSemester = $request->query('semester');

        if ($selectedProgramId && ! Program::query()->whereKey($selectedProgramId)->exists()) {
            $selectedProgramId = null;
        }

        if (! in_array($selectedYearLevel, [1, 2, 3, 4], true)) {
            $selectedYearLevel = null;
        }

        if (! in_array($selectedSemester, ['First Semester', 'Second Semester', 'Summer'], true)) {
            $selectedSemester = null;
        }

        return view('super-admin.subjects', [
            'programs' => $this->programsList(),
            'subjectMappings' => $this->subjectMappings($selectedProgramId, $selectedYearLevel, $selectedSemester),
            'selectedProgramId' => $selectedProgramId,
            'selectedYearLevel' => $selectedYearLevel,
            'selectedSemester' => $selectedSemester,
            'hasSubjectFilters' => $selectedProgramId || $selectedYearLevel || $selectedSemester,
            'activeSemester' => AcademicSetting::query()->value('active_semester'),
        ]);
    }

    public function storeCollege(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_name' => ['required', 'string', 'max:255', 'unique:colleges,college_name'],
        ]);

        $college = College::create($validated);

        Log::info('College created by super admin.', [
            'actor_id' => Auth::id(),
            'college_id' => $college->college_id,
            'college_name' => $college->college_name,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'College added successfully.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $request->input('college_id')),
            ],
        ]);

        $department = Department::create($validated);

        Log::info('Department created by super admin.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $department->dept_name,
            'college_id' => $department->college_id,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'Department added successfully.');
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $request->input('college_id')),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $program = Program::create($validated);

        Log::info('Program created by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('super-admin.programs')
            ->with('status', 'Program added successfully.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,program_id'],
            'subject_code' => ['required', 'string', 'max:255'],
            'subject_name' => ['required', 'string', 'max:255'],
            'year_level' => ['required', Rule::in([1, 2, 3, 4])],
            'semester' => ['required', Rule::in(['First Semester', 'Second Semester', 'Summer'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $subject = Subject::updateOrCreate(
            ['subject_code' => $validated['subject_code']],
            [
                'subject_name' => $validated['subject_name'],
                'is_active' => $validated['is_active'],
            ],
        );

        $mapping = SubjectProgram::firstOrCreate(
            [
                'subject_id' => $subject->subject_id,
                'program_id' => $validated['program_id'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
            ],
        );

        Log::info('Subject mapped by super admin.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'program_id' => $validated['program_id'],
            'subject_program_id' => $mapping->subject_program_id,
            'year_level' => $validated['year_level'],
            'semester' => $validated['semester'],
        ]);

        return redirect()
            ->route('super-admin.subjects', [
                'program' => $validated['program_id'],
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

    public function updateSubject(Request $request, SubjectProgram $subjectProgram): RedirectResponse
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
            'semester' => ['required', Rule::in(['First Semester', 'Second Semester', 'Summer'])],
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

        return redirect()
            ->route('super-admin.subjects', [
                'program' => $validated['program_id'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
            ])
            ->with('status', 'Subject updated successfully.');
    }

    public function destroySubject(Request $request, SubjectProgram $subjectProgram): RedirectResponse
    {
        $subjectProgram->loadMissing(['subject', 'program']);
        $subject = $subjectProgram->subject;
        $programId = $subjectProgram->program_id;
        $yearLevel = $request->integer('year_level');
        $semester = $request->query('semester');
        $redirectFilters = [
            'program' => $request->integer('program') ?: $programId,
            'year_level' => in_array($yearLevel, [1, 2, 3, 4], true) ? $yearLevel : null,
            'semester' => in_array($semester, ['First Semester', 'Second Semester', 'Summer'], true) ? $semester : null,
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

    public function destroyCollege(College $college): RedirectResponse
    {
        $collegeName = $college->college_name;
        $departmentCount = $college->departments()->count();
        $programCount = $college->programs()->count();

        try {
            $college->delete();
        } catch (Throwable $exception) {
            Log::warning('College delete failed.', [
                'actor_id' => Auth::id(),
                'college_id' => $college->college_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('super-admin.colleges')
                ->withErrors("Unable to delete {$collegeName} right now. Please try again.");
        }

        Log::info('College deleted by super admin.', [
            'actor_id' => Auth::id(),
            'college_id' => $college->college_id,
            'college_name' => $collegeName,
            'departments_removed' => $departmentCount,
            'programs_removed' => $programCount,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', "{$collegeName} deleted successfully.");
    }

    public function destroyDepartment(Department $department): RedirectResponse
    {
        $departmentName = $department->dept_name;
        $instructorCount = $department->instructorProfiles()->count();

        try {
            $department->delete();
        } catch (Throwable $exception) {
            Log::warning('Department delete failed.', [
                'actor_id' => Auth::id(),
                'department_id' => $department->department_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('super-admin.colleges')
                ->withErrors("Unable to delete {$departmentName} right now. Please try again.");
        }

        Log::info('Department deleted by super admin.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $departmentName,
            'instructor_profiles_affected' => $instructorCount,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', "{$departmentName} deleted successfully.");
    }

    private function countUsersByRole(string $roleName): int
    {
        return DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('roles.role_name', $roleName)
            ->count();
    }

    private function colleges()
    {
        return College::withCount('departments')
            ->orderBy('college_name')
            ->get();
    }

    private function departments()
    {
        return Department::with('college')
            ->withCount('instructorProfiles')
            ->orderBy('dept_name')
            ->get();
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
