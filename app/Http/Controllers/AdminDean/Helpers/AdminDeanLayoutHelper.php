<?php

namespace App\Http\Controllers\AdminDean\Helpers;

use App\Models\User;
use Illuminate\Support\Str;

trait AdminDeanLayoutHelper
{
    protected function sharedData(string $activeNav): array
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
}
