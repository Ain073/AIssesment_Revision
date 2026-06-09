<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedDepartmentId = $scopedDepartment?->department_id;
        $teacherQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->whereRaw('1 = 0')
            );

        return view('department-chair.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Subjects',
                    'value' => Subject::count(),
                    'caption' => 'Subject records currently stored',
                    'icon' => 'menu_book',
                ],
                [
                    'label' => 'Teachers',
                    'value' => (clone $teacherQuery)->count(),
                    'caption' => 'Instructor profiles in your department',
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => 0,
                    'caption' => 'Student accounts under related programs',
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
                    'label' => 'Open Subjects',
                    'description' => 'Prepare subject records for your department.',
                    'href' => route('department-chair.subjects'),
                    'icon' => 'menu_book',
                ],
                [
                    'label' => 'View Teachers',
                    'description' => 'Check instructor records under your department.',
                    'href' => route('department-chair.teachers'),
                    'icon' => 'badge',
                ],
                [
                    'label' => 'View Reports',
                    'description' => 'Monitor finalized instructor reports.',
                    'href' => route('department-chair.reports'),
                    'icon' => 'summarize',
                ],
            ],
            'focusItems' => [
                [
                    'title' => 'Department-scoped academic setup',
                    'description' => 'This portal is for subjects, teachers, students, and reports under the assigned department scope.',
                ],
                [
                    'title' => 'Instructor functions stay available',
                    'description' => 'If the account also acts as an instructor, classes, assessments, and report writing still stay in the Instructor mode.',
                ],
            ],
        ]);
    }

    public function subjects(): View
    {
        $user = $this->currentUser();

        return view('department-chair.subjects', $this->sharedData($user, 'subjects') + [
            'scopedDepartment' => $this->scopedDepartment($user),
            'subjects' => Subject::query()
                ->orderBy('subject_code')
                ->orderBy('subject_name')
                ->get(),
            'totalSubjects' => Subject::count(),
            'activeSubjects' => Subject::where('is_active', true)->count(),
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:255', 'unique:subjects,subject_code'],
            'subject_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $subject = Subject::create($validated);

        Log::info('Subject created by department chair.', [
            'actor_id' => Auth::id(),
            'subject_id' => $subject->subject_id,
            'subject_code' => $subject->subject_code,
            'subject_name' => $subject->subject_name,
        ]);

        return redirect()
            ->route('department-chair.subjects')
            ->with('status', 'Subject added successfully.');
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
                fn ($query) => $query->whereRaw('1 = 0')
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

    public function students(): View
    {
        return view('department-chair.students', $this->placeholderPageData(
            'students',
            'Students',
            'Student accounts related to the department programs will be visible here.',
            [
                'View student accounts under related programs',
                'Monitor student profile completeness',
                'Prepare records used by roster and class flows',
            ],
        ));
    }

    public function reports(): View
    {
        return view('department-chair.reports', $this->placeholderPageData(
            'reports',
            'Reports',
            'Finalized instructor reports submitted for department monitoring will be organized here.',
            [
                'Review finalized formative reports',
                'Review finalized summative reports',
                'Monitor report outputs across instructors',
            ],
        ));
    }

    private function placeholderPageData(
        string $activeNav,
        string $pageHeading,
        string $pageDescription,
        array $checklist
    ): array {
        $user = $this->currentUser();

        return $this->sharedData($user, $activeNav) + [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'pageChecklist' => $checklist,
        ];
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
            ['key' => 'subjects', 'label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('department-chair.subjects')],
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
}
