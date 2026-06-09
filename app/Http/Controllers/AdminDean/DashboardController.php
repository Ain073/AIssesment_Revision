<?php

namespace App\Http\Controllers\AdminDean;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
                    'value' => Department::when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))->count(),
                    'caption' => 'Academic units under your college',
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Programs',
                    'value' => Program::when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))->count(),
                    'caption' => 'Degree programs to organize',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Teachers',
                    'value' => User::query()
                        ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
                        ->whereHas(
                            'instructorProfile.department',
                            fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId))
                        )
                        ->count(),
                    'caption' => 'Faculty accounts under your scope',
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => 0,
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
            'focusItems' => [
                [
                    'title' => 'Scoped college assignment still pending',
                    'description' => 'This dashboard is ready, but the college-specific data will start making sense once we attach a real college scope to the dean role.',
                ],
                [
                    'title' => 'Program structure comes next',
                    'description' => 'Students should belong to programs, so this module is one of the next core foundations.',
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
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))
                ->withCount('instructorProfiles')
                ->orderBy('dept_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('departments')])
                : College::withCount('departments')->orderBy('college_name')->get(),
            'scopedCollege' => $scopedCollege,
            'totalDepartments' => Department::when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))->count(),
            'totalColleges' => $scopedCollege ? 1 : College::count(),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        $validated = $request->validate([
            'college_id' => [$scopedCollegeId ? 'nullable' : 'required', 'exists:colleges,college_id'],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $scopedCollegeId ?? $request->input('college_id')),
            ],
        ]);

        $validated['college_id'] = $scopedCollegeId ?? (int) $validated['college_id'];

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
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))
                ->withCount('studentProfiles')
                ->orderBy('program_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('programs')])
                : College::withCount('programs')->orderBy('college_name')->get(),
            'scopedCollege' => $scopedCollege,
            'totalPrograms' => Program::when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId))->count(),
            'totalColleges' => $scopedCollege ? 1 : College::count(),
        ]);
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        $validated = $request->validate([
            'college_id' => [$scopedCollegeId ? 'nullable' : 'required', 'exists:colleges,college_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $scopedCollegeId ?? $request->input('college_id')),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['college_id'] = $scopedCollegeId ?? (int) $validated['college_id'];

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
                fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId))
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
            'totalTeachers' => $teachers->count(),
            'totalAdminDeans' => $adminDeans->count(),
            'totalDepartmentChairs' => $departmentChairs->count(),
        ]);
    }

    public function students(): View
    {
        return view('admin-dean.students', $this->placeholderPageData(
            'students',
            'Students',
            'Student records grouped by program will appear here.',
            [
                'Student list by program',
                'Program filters and counts',
                'College-level monitoring view',
            ],
        ));
    }

    private function placeholderPageData(
        string $activeNav,
        string $pageHeading,
        string $pageDescription,
        array $checklist
    ): array {
        return $this->sharedData($activeNav) + [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'pageChecklist' => $checklist,
        ];
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
}
