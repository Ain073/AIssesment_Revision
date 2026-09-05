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
            'must_change_password' => false,
        ]);

        return back()->with('status', 'Password updated.');
    }

    private function profileUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing(
            'roles',
            'studentProfile.program.department.college',
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
            'showTopbarSearch' => false,
        ];
    }

    private function detailRows(User $user): array
    {
        if ($user->studentProfile) {
            $program = $user->studentProfile->program;
            $programAndCollege = collect([
                $program?->program_name,
                $program?->department?->dept_name,
                $program?->department?->college?->college_name,
            ])->filter()->join(' - ');

            return [
                ['label' => 'Student Number', 'value' => $user->studentProfile->student_number ?? 'Not assigned'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Program / Department / College', 'value' => $programAndCollege ?: 'Not assigned'],
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
            return $this->markNavActive([
                ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active_route' => 'super-admin.dashboard'],
                ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active_route' => 'super-admin.colleges'],
                ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active_route' => 'super-admin.programs'],
                ['label' => 'Dean Designation', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active_route' => 'super-admin.roles'],
                ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active_route' => 'super-admin.users*'],
            ]);
        }

        $items = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $items = array_merge($items, [
                ['label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes'), 'active_route' => 'instructor.classes*'],
                ['label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments'), 'active_route' => 'instructor.assessments*'],
                ['label' => 'Reports', 'icon' => 'summarize', 'href' => route('instructor.reports'), 'active_route' => 'instructor.reports*'],
            ]);
        }

        if ($user->hasRole('admin_dean')) {
            $items = array_merge($items, [
                ['label' => 'Departments', 'icon' => 'apartment', 'href' => route('admin-dean.departments'), 'active_route' => 'admin-dean.departments'],
                ['label' => 'Users', 'icon' => 'groups', 'href' => route('admin-dean.teachers'), 'active_route' => 'admin-dean.teachers'],
                ['label' => 'Chair Designation', 'icon' => 'admin_panel_settings', 'href' => route('admin-dean.designations'), 'active_route' => 'admin-dean.designations'],
            ]);
        }

        if ($user->hasRole('department_chair')) {
            $items = array_merge($items, [
                ['label' => 'Users', 'icon' => 'groups', 'href' => route('department-chair.teachers'), 'active_route' => ['department-chair.teachers', 'department-chair.students*']],
                ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('department-chair.subjects'), 'active_route' => 'department-chair.subjects*'],
            ]);
        }

        if (! empty($items)) {
            array_unshift($items, $this->dashboardNavItem($user));

            return $this->markNavActive($items);
        }

        return $this->markNavActive([
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard'), 'active_route' => 'student.dashboard'],
            ['label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes'), 'active_route' => 'student.classes*'],
            ['label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments'), 'active_route' => 'student.assessments*'],
            ['label' => 'Results', 'icon' => 'grading', 'href' => route('student.results'), 'active_route' => 'student.results'],
        ]);
    }

    private function markNavActive(array $items): array
    {
        return array_map(function (array $item): array {
            $activeRoute = (array) $item['active_route'];
            unset($item['active_route']);

            return $item + ['active' => request()->routeIs(...$activeRoute)];
        }, $items);
    }

    private function dashboardNavItem(User $user): array
    {
        $href = route('instructor.dashboard');
        $activeRoutes = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $activeRoutes[] = 'instructor.dashboard';
        }

        if ($user->hasRole('admin_dean')) {
            $activeRoutes[] = 'admin-dean.dashboard';
        }

        if ($user->hasRole('department_chair')) {
            $activeRoutes[] = 'department-chair.dashboard';
        }

        if (request()->routeIs('admin-dean.*') && $user->hasRole('admin_dean')) {
            $href = route('admin-dean.dashboard');
        } elseif (request()->routeIs('department-chair.*') && $user->hasRole('department_chair')) {
            $href = route('department-chair.dashboard');
        }

        return [
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'href' => $href,
            'active_route' => $activeRoutes ?: 'instructor.dashboard',
        ];
    }

    private function portalSubtitle(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'Admin Panel';
        }

        if ($user->hasRole('admin_dean')) {
            return 'Dean';
        }

        if ($user->hasRole('department_chair')) {
            return 'Department Chair';
        }

        if ($user->hasRole('instructor')) {
            return 'Instructor';
        }

        return 'Student';
    }

    private function profileMeta(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'Admin Account';
        }

        return $this->roleLabel($user).' Account';
    }

    private function roleLabel(User $user): string
    {
        $labels = [
            'super_admin' => 'Admin',
            'admin_dean' => 'Dean',
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
