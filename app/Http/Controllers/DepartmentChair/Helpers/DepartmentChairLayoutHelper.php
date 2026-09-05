<?php

namespace App\Http\Controllers\DepartmentChair\Helpers;

use App\Models\User;
use Illuminate\Support\Str;

trait DepartmentChairLayoutHelper
{
    protected function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Department Chair',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Department Chair Account',
            'navItems' => $this->navItems($user),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    private function navItems(User $user): array
    {
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
        }

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
}
