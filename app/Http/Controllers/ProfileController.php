<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = $this->profileUser();

        return view('profile.index', $this->layoutData($user) + [
            'user' => $user,
            'detailRows' => $this->detailRows($user),
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $this->profileUser();
        $path = $validated['profile_photo']->store('profile-photos', 'public');

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update([
            'profile_photo_path' => $path,
        ]);

        return back()->with('status', 'Profile picture updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->profileUser()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('status', 'Password updated.');
    }

    private function profileUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing(
            'roles',
            'studentProfile.program.college',
            'instructorProfile.department.college',
        );

        return $user;
    }

    private function layoutData(User $user): array
    {
        return [
            'portalSubtitle' => $this->portalSubtitle($user),
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => $this->profileMeta($user),
            'navItems' => $this->navItems($user),
            'viewSwitches' => $this->viewSwitches($user),
            'showTopbarSearch' => false,
        ];
    }

    private function detailRows(User $user): array
    {
        if ($user->studentProfile) {
            $program = $user->studentProfile->program;
            $programAndCollege = collect([
                $program?->program_name,
                $program?->college?->college_name,
            ])->filter()->join(' - ');

            return [
                ['label' => 'Student Number', 'value' => $user->studentProfile->student_number ?? 'Not assigned'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Program / College', 'value' => $programAndCollege ?: 'Not assigned'],
            ];
        }

        if ($user->instructorProfile) {
            $department = $user->instructorProfile->department;
            $departmentAndCollege = collect([
                $department?->dept_name,
                $department?->college?->college_name,
            ])->filter()->join(' - ');

            return [
                ['label' => 'Employee Number', 'value' => $user->instructorProfile->employee_number ?? 'Not assigned'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Department / College', 'value' => $departmentAndCollege ?: 'Not assigned'],
            ];
        }

        return [
            ['label' => 'Email', 'value' => $user->email],
        ];
    }

    private function navItems(User $user): array
    {
        if ($user->hasRole('super_admin')) {
            return $this->markActive([
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard')],
                ['key' => 'colleges', 'label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges')],
                ['key' => 'programs', 'label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs')],
                ['key' => 'subjects', 'label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects')],
                ['key' => 'roles', 'label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles')],
                ['key' => 'users', 'label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users')],
            ]);
        }

        if ($user->hasRole('admin_dean')) {
            return $this->markActive([
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('admin-dean.dashboard')],
                ['key' => 'departments', 'label' => 'Departments', 'icon' => 'apartment', 'href' => route('admin-dean.departments')],
                ['key' => 'programs', 'label' => 'Programs', 'icon' => 'school', 'href' => route('admin-dean.programs')],
                ['key' => 'teachers', 'label' => 'Teachers', 'icon' => 'badge', 'href' => route('admin-dean.teachers')],
                ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('admin-dean.students')],
            ]);
        }

        if ($user->hasRole('department_chair')) {
            return $this->markActive([
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('department-chair.dashboard')],
                ['key' => 'teachers', 'label' => 'Teachers', 'icon' => 'badge', 'href' => route('department-chair.teachers')],
                ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('department-chair.students')],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'summarize', 'href' => route('department-chair.reports')],
            ]);
        }

        if ($user->hasRole('instructor')) {
            return $this->markActive([
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('instructor.dashboard')],
                ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes')],
                ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments')],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'summarize', 'href' => route('instructor.reports')],
            ]);
        }

        return $this->markActive([
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments')],
            ['key' => 'results', 'label' => 'Results', 'icon' => 'grading', 'href' => route('student.results')],
        ]);
    }

    private function markActive(array $items): array
    {
        return array_map(fn (array $item) => $item + ['active' => false], $items);
    }

    private function viewSwitches(User $user): array
    {
        $switches = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Instructor',
                'icon' => 'co_present',
                'href' => route('instructor.dashboard'),
                'active' => false,
            ];
        }

        if ($user->hasRole('admin_dean')) {
            $switches[] = [
                'label' => 'Admin/Dean',
                'icon' => 'supervisor_account',
                'href' => route('admin-dean.dashboard'),
                'active' => false,
            ];
        }

        if ($user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Dept Chair',
                'icon' => 'assignment_ind',
                'href' => route('department-chair.dashboard'),
                'active' => false,
            ];
        }

        return $switches;
    }

    private function portalSubtitle(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'Super Admin Panel';
        }

        if ($user->hasRole('admin_dean')) {
            return 'Admin/Dean Portal';
        }

        if ($user->hasRole('department_chair')) {
            return 'Department Chair Portal';
        }

        if ($user->hasRole('instructor')) {
            return 'Instructor Portal';
        }

        return 'Student Portal';
    }

    private function profileMeta(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'System Controller';
        }

        return $this->roleLabel($user).' Account';
    }

    private function roleLabel(User $user): string
    {
        $labels = [
            'super_admin' => 'Super Admin',
            'admin_dean' => 'Admin/Dean',
            'department_chair' => 'Department Chair',
            'instructor' => 'Instructor',
            'student' => 'Student',
        ];

        return $user->roles
            ->pluck('role_name')
            ->map(fn (string $role) => $labels[$role] ?? Str::headline($role))
            ->join(', ');
    }
}
