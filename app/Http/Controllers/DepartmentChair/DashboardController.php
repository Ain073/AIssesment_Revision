<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccountImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedDepartmentId = $scopedDepartment?->department_id;
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $scopedProgramIds = $scopedPrograms->pluck('program_id');
        $teacherQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            );

        return view('department-chair.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Teachers',
                    'value' => (clone $teacherQuery)->count(),
                    'caption' => 'Instructor profiles in your department',
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => $scopedProgramIds->isNotEmpty()
                        ? StudentProfile::query()->whereIn('program_id', $scopedProgramIds)->count()
                        : 0,
                    'caption' => 'Student accounts under your current program scope',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Reports',
                    'value' => 0,
                    'caption' => 'Finalized instructor reports to monitor',
                    'icon' => 'summarize',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'View Teachers',
                    'description' => 'Check instructor records under your department.',
                    'href' => route('department-chair.teachers'),
                    'icon' => 'badge',
                ],
                [
                    'label' => 'View Students',
                    'description' => 'Check student records under your current program scope.',
                    'href' => route('department-chair.students'),
                    'icon' => 'groups',
                ],
                [
                    'label' => 'View Reports',
                    'description' => 'Monitor finalized instructor reports.',
                    'href' => route('department-chair.reports'),
                    'icon' => 'summarize',
                ],
            ],
        ]);
    }

    public function teachers(): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedDepartmentId = $scopedDepartment?->department_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        return view('department-chair.teachers', $this->sharedData($user, 'teachers') + [
            'teachers' => $teachers,
            'scopedDepartment' => $scopedDepartment,
            'totalTeachers' => $teachers->count(),
            'activeTeachers' => $teachers->where('status', 'active')->count(),
            'elevatedTeachers' => $teachers
                ->filter(fn (User $teacher) => $teacher->hasRole('admin_dean') || $teacher->hasRole('department_chair'))
                ->count(),
        ]);
    }

    public function students(Request $request, StudentAccountImportService $importer): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $scopedProgramIds = $scopedPrograms->pluck('program_id');
        $students = $scopedProgramIds->isNotEmpty()
            ? StudentProfile::query()
                ->with(['user.roles', 'program.college'])
                ->whereIn('program_id', $scopedProgramIds)
                ->get()
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values()
            : collect();

        return view('department-chair.students', $this->sharedData($user, 'students') + [
            'students' => $students,
            'scopedDepartment' => $scopedDepartment,
            'scopedPrograms' => $scopedPrograms,
            'totalStudents' => $students->count(),
            'activeStudents' => $students->filter(fn (StudentProfile $student) => $student->user?->status === 'active')->count(),
            'programCount' => $scopedPrograms->count(),
            'studentImportPreview' => $importer->previewForRequest($request, $this->studentImportScope($scopedDepartment)),
        ]);
    }

    public function downloadStudentImportSample(StudentAccountImportService $importer): StreamedResponse
    {
        $user = $this->currentUser();
        $program = $this->scopedPrograms($this->scopedDepartment($user))->first();

        abort_unless($program, 404, 'No program is available for student account import.');

        return $importer->sampleCsv($program);
    }

    public function previewStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $programs = $this->scopedPrograms($department);

        abort_unless($department && $programs->isNotEmpty(), 403, 'A department and related program are required before importing students.');

        $token = $importer->previewUpload($request, $programs, $this->studentImportScope($department));

        return redirect()->route('department-chair.students', ['import_token' => $token]);
    }

    public function confirmStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $programs = $this->scopedPrograms($department);

        abort_unless($department && $programs->isNotEmpty(), 403);

        $result = $importer->confirm($request, $programs, $this->studentImportScope($department));

        $redirect = redirect()
            ->route('department-chair.students')
            ->with('status', $result['created_count'].' student accounts created successfully.');

        if ($result['setup_links_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some password setup emails were not delivered. Those students can request a new link through Forgot Password.');
        }

        return $redirect;
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $programIds = $scopedPrograms->pluck('program_id')->all();

        abort_unless($scopedDepartment, 403, 'Department Chair account needs an assigned department before creating users.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(['instructor', 'student'])],
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

        DB::transaction(function () use ($validated, $user, $scopedDepartment) {
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
                    'department_id' => $scopedDepartment->department_id,
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

            Log::info('User account created by department chair.', [
                'actor_id' => $user->id,
                'user_id' => $createdUser->id,
                'base_role' => $validated['base_role'],
                'department_id' => $scopedDepartment->department_id,
                'program_id' => $validated['program_id'] ?? null,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'User account created successfully.');
    }

    public function reports(): View
    {
        $user = $this->currentUser();

        return view('department-chair.reports', $this->sharedData($user, 'reports'));
    }

    private function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Department Chair Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Department Chair Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => $this->viewSwitches($user, 'department_chair'),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    private function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('department-chair.dashboard')],
            ['key' => 'teachers', 'label' => 'Teachers', 'icon' => 'badge', 'href' => route('department-chair.teachers')],
            ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('department-chair.students')],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'summarize', 'href' => route('department-chair.reports')],
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

    private function scopedDepartment(User $user): ?Department
    {
        return $user->instructorProfile?->department;
    }

    /**
     * @return Collection<int, Program>
     */
    private function scopedPrograms(?Department $department): Collection
    {
        if (! $department) {
            return collect();
        }

        return Program::query()
            ->with('college')
            ->where('college_id', $department->college_id)
            ->withCount('studentProfiles')
            ->orderBy('program_name')
            ->get();
    }

    private function studentImportScope(?Department $department): string
    {
        return 'department:'.(int) $department?->department_id;
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
