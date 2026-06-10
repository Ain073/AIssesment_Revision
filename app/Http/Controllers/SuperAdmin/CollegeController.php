<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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

    public function subjects(): View
    {
        return view('super-admin.subjects', [
            'programs' => $this->programsList(),
            'subjectMappings' => $this->subjectMappings(),
            'totalSubjects' => Subject::count(),
            'totalSubjectMappings' => SubjectProgram::count(),
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
            ->route('super-admin.subjects')
            ->with('status', 'Subject saved and mapped to the selected program.');
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

    private function subjectMappings()
    {
        return SubjectProgram::with(['program.college', 'subject'])
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
