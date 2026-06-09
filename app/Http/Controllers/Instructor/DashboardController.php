<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\InstructorProfile;
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
        $instructorProfile = $this->instructorProfile($user);
        $classesCount = $instructorProfile?->classes()->count() ?? 0;

        return view('instructor.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Classes Handled',
                    'value' => $classesCount,
                    'caption' => 'Classes currently linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Students',
                    'value' => 0,
                    'caption' => 'Across your active classes',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Assessments',
                    'value' => 0,
                    'caption' => 'Drafts and published items',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Pending Checks',
                    'value' => 0,
                    'caption' => 'Submissions waiting for review',
                    'icon' => 'fact_check',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'View Classes',
                    'description' => 'Open your subject and section list.',
                    'href' => route('instructor.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Open Assessments',
                    'description' => 'Manage quizzes, exams, and activities.',
                    'href' => route('instructor.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Browse Students',
                    'description' => 'Review student rosters by class.',
                    'href' => route('instructor.students'),
                    'icon' => 'groups',
                ],
            ],
            'pendingWork' => [
                [
                    'title' => 'No classes linked yet',
                    'description' => 'Once classes are assigned to this instructor, the next teaching tasks will appear here.',
                    'state' => 'Waiting for setup',
                ],
                [
                    'title' => 'No assessments in draft',
                    'description' => 'Draft assessments will be listed here for quick continuation.',
                    'state' => 'Ready',
                ],
                [
                    'title' => 'No submissions to check',
                    'description' => 'Review tasks will surface here once student work starts coming in.',
                    'state' => 'Clear',
                ],
            ],
        ]);
    }

    public function classes(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->latest('class_id')
                ->get()
            : collect();

        return view('instructor.classes', $this->sharedData($user, 'classes') + [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'totalClasses' => $classes->count(),
            'latestSchoolYear' => $classes->pluck('school_year')->filter()->unique()->first(),
        ]);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating classes.');

        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $class = $instructorProfile->classes()->create($validated);

        Log::info('Class created by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $class->class_id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'class_name' => $class->class_name,
            'school_year' => $class->school_year,
        ]);

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class added successfully.');
    }

    public function assessments(): View
    {
        return view('instructor.assessments', $this->placeholderPageData(
            'assessments',
            'Assessments',
            'This is where we will build quiz, exam, and activity management for instructors.',
            [
                'Create and edit assessments',
                'Publish and monitor availability',
                'Track completion status by class',
            ],
        ));
    }

    public function students(): View
    {
        return view('instructor.students', $this->placeholderPageData(
            'students',
            'Students',
            'Student lists per class or section will live here once we connect enrollment data.',
            [
                'Roster per subject or section',
                'Student status and participation view',
                'Shortcuts to submissions and results',
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
            'portalSubtitle' => 'Instructor Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Instructor Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => $this->viewSwitches($user, 'instructor'),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    private function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('instructor.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments')],
            ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('instructor.students')],
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

    private function instructorProfile(User $user): ?InstructorProfile
    {
        return $user->instructorProfile;
    }
}
