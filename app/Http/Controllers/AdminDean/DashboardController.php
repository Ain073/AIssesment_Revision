<?php

namespace App\Http\Controllers\AdminDean;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccountImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.dashboard', $this->sharedData('dashboard') + [
            'user' => $user,
            'stats' => [
                [
                    'label' => 'Departments',
                    'value' => Department::query()
                        ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->count(),
                    'caption' => 'Academic units under your college',
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Programs',
                    'value' => Program::query()
                        ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->count(),
                    'caption' => 'Degree programs to organize',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Teachers',
                    'value' => User::query()
                        ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
                        ->whereHas(
                            'instructorProfile.department',
                            fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
                        )
                        ->count(),
                    'caption' => 'Faculty accounts under your scope',
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => StudentProfile::query()
                        ->whereHas('program', fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0')))
                        ->count(),
                    'caption' => 'Students grouped by program',
                    'icon' => 'groups',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Departments',
                    'description' => 'Review department records under your college.',
                    'href' => route('admin-dean.departments'),
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Manage Programs',
                    'description' => 'Prepare the program structure for students.',
                    'href' => route('admin-dean.programs'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'View Teachers',
                    'description' => 'Check the teacher accounts in your area.',
                    'href' => route('admin-dean.teachers'),
                    'icon' => 'badge',
                ],
            ],
        ]);
    }

    public function departments(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.departments', $this->sharedData('departments') + [
            'departments' => Department::with('college')
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->withCount('instructorProfiles')
                ->orderBy('dept_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('departments')])
                : collect(),
            'scopedCollege' => $scopedCollege,
            'totalDepartments' => Department::query()
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->count(),
            'totalColleges' => $scopedCollege ? 1 : 0,
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating departments.');

        $validated = $request->validate([
            'college_id' => ['nullable', 'integer', Rule::in([$scopedCollegeId])],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $scopedCollegeId),
            ],
        ]);

        $validated['college_id'] = $scopedCollegeId;

        $department = Department::create($validated);

        Log::info('Department created by admin/dean.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $department->dept_name,
            'college_id' => $department->college_id,
        ]);

        return redirect()
            ->route('admin-dean.departments')
            ->with('status', 'Department added successfully.');
    }

    public function programs(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.programs', $this->sharedData('programs') + [
            'programs' => Program::with('college')
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->withCount('studentProfiles')
                ->orderBy('program_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('programs')])
                : collect(),
            'scopedCollege' => $scopedCollege,
            'totalPrograms' => Program::query()
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->count(),
            'totalColleges' => $scopedCollege ? 1 : 0,
        ]);
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating programs.');

        $validated = $request->validate([
            'college_id' => ['nullable', 'integer', Rule::in([$scopedCollegeId])],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $scopedCollegeId),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['college_id'] = $scopedCollegeId;

        $program = Program::create($validated);

        Log::info('Program created by admin/dean.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('admin-dean.programs')
            ->with('status', 'Program added successfully.');
    }

    public function teachers(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        $adminDeans = $teachers->filter->hasRole('admin_dean')->values();
        $departmentChairs = $teachers->filter->hasRole('department_chair')->values();

        return view('admin-dean.teachers', $this->sharedData('teachers') + [
            'teachers' => $teachers,
            'scopedCollege' => $scopedCollege,
            'departments' => Department::query()
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('dept_name')
                ->get(),
            'totalTeachers' => $teachers->count(),
            'totalAdminDeans' => $adminDeans->count(),
            'totalDepartmentChairs' => $departmentChairs->count(),
        ]);
    }

    public function students(Request $request, StudentAccountImportService $importer): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;
        $programs = Program::query()
            ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('program_name')
            ->get();

        $students = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->whereHas('program', fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0')))
            ->get()
            ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
            ->values();

        return view('admin-dean.students', $this->sharedData('students') + [
            'students' => $students,
            'programs' => $programs,
            'scopedCollege' => $scopedCollege,
            'totalStudents' => $students->count(),
            'activeStudents' => $students->filter(fn (StudentProfile $student) => $student->user?->status === 'active')->count(),
            'programCount' => $programs->count(),
            'studentImportPreview' => $importer->previewForRequest($request, $this->studentImportScope($scopedCollege)),
        ]);
    }

    public function downloadStudentImportSample(StudentAccountImportService $importer): StreamedResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $program = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->first();

        abort_unless($program, 404, 'No program is available for student account import.');

        return $importer->sampleCsv($program);
    }

    public function previewStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $programs = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->get();

        abort_unless($college && $programs->isNotEmpty(), 403, 'A college and related program are required before importing students.');

        $token = $importer->previewUpload($request, $programs, $this->studentImportScope($college));

        return redirect()->route('admin-dean.students', ['import_token' => $token]);
    }

    public function confirmStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $programs = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->get();

        abort_unless($college && $programs->isNotEmpty(), 403);

        $result = $importer->confirm($request, $programs, $this->studentImportScope($college));
        $redirect = redirect()
            ->route('admin-dean.students')
            ->with('status', $result['created_count'].' student accounts created successfully.');

        if ($result['setup_links_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some password setup emails were not delivered. Those students can request a new link through Forgot Password.');
        }

        return $redirect;
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating users.');

        $departmentIds = Department::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('department_id')
            ->all();
        $programIds = Program::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('program_id')
            ->all();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(['instructor', 'student'])],
            'department_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'integer',
                Rule::in($departmentIds),
            ],
            'employee_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'string',
                'max:255',
                'unique:instructor_profiles,employee_number',
            ],
            'program_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'integer',
                Rule::in($programIds),
            ],
            'student_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'string',
                'max:255',
                'unique:student_profiles,student_number',
            ],
        ]);

        DB::transaction(function () use ($validated, $user, $scopedCollege) {
            $createdUser = User::create([
                'name' => $this->buildName($validated),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'],
            ]);

            $role = Role::where('role_name', $validated['base_role'])->firstOrFail();
            $createdUser->roles()->attach($role->role_id);

            if ($validated['base_role'] === 'instructor') {
                InstructorProfile::create([
                    'user_id' => $createdUser->id,
                    'department_id' => $validated['department_id'],
                    'employee_number' => $validated['employee_number'],
                ]);
            }

            if ($validated['base_role'] === 'student') {
                StudentProfile::create([
                    'user_id' => $createdUser->id,
                    'program_id' => $validated['program_id'],
                    'student_number' => $validated['student_number'],
                ]);
            }

            Log::info('User account created by admin/dean.', [
                'actor_id' => $user->id,
                'user_id' => $createdUser->id,
                'base_role' => $validated['base_role'],
                'college_id' => $scopedCollege->college_id,
                'department_id' => $validated['department_id'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'User account created successfully.');
    }

    private function sharedData(string $activeNav): array
    {
        $user = $this->currentUser();

        return [
            'user' => $user,
            'portalSubtitle' => 'Admin/Dean Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Admin/Dean Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => $this->viewSwitches($user, 'admin_dean'),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    private function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('admin-dean.dashboard')],
            ['key' => 'departments', 'label' => 'Departments', 'icon' => 'apartment', 'href' => route('admin-dean.departments')],
            ['key' => 'programs', 'label' => 'Programs', 'icon' => 'school', 'href' => route('admin-dean.programs')],
            ['key' => 'teachers', 'label' => 'Teachers', 'icon' => 'badge', 'href' => route('admin-dean.teachers')],
            ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('admin-dean.students')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    private function viewSwitches(User $user, string $activeMode): array
    {
        $switches = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Instructor',
                'icon' => 'co_present',
                'href' => route('instructor.dashboard'),
                'active' => $activeMode === 'instructor',
            ];
        }

        if ($user->hasRole('admin_dean')) {
            $switches[] = [
                'label' => 'Admin/Dean',
                'icon' => 'supervisor_account',
                'href' => route('admin-dean.dashboard'),
                'active' => $activeMode === 'admin_dean',
            ];
        }

        if ($user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Dept Chair',
                'icon' => 'assignment_ind',
                'href' => route('department-chair.dashboard'),
                'active' => $activeMode === 'department_chair',
            ];
        }

        return $switches;
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    private function scopedCollege(User $user): ?College
    {
        $user->loadMissing('instructorProfile.department.college');

        return $user->instructorProfile?->department?->college;
    }

    private function studentImportScope(?College $college): string
    {
        return 'college:'.(int) $college?->college_id;
    }

    private function buildName(array $validated): string
    {
        return collect([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ])->filter()->implode(' ');
    }
}
