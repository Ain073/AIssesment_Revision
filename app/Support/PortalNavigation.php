<?php

namespace App\Support;

use App\Models\User;

class PortalNavigation
{
    /** Build the same role-scoped menu for feature pages and the profile. */
    public static function forUser(User $user): array
    {
        if ($user->hasRole('super_admin')) {
            return self::items([
                ['Dashboard', 'dashboard', 'super-admin.dashboard'],
                ['Colleges & Departments', 'account_balance', 'super-admin.colleges'],
                ['Programs', 'school', 'super-admin.programs'],
                ['Dean Designation', 'admin_panel_settings', 'super-admin.roles'],
                ['Passing Rates', 'percent', 'super-admin.passing-rates'],
                ['Users', 'person_search', 'super-admin.users'],
                ['Audit Trail', 'fact_check', 'super-admin.audit-logs'],
            ]);
        }

        $items = [];
        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $items = [
                ['Classes', 'school', 'instructor.classes'],
                ['Assessments', 'assignment', 'instructor.assessments'],
                ['Reports', 'summarize', 'instructor.reports'],
            ];
        }
        if ($user->hasRole('admin_dean')) {
            $items = array_merge($items, [
                ['Departments', 'apartment', 'admin-dean.departments'],
                ['Users', 'groups', 'admin-dean.teachers'],
                ['Chair Designation', 'admin_panel_settings', 'admin-dean.designations'],
            ]);
        }
        if ($user->hasRole('department_chair')) {
            $items = array_merge($items, [
                ['Programs', 'school', 'department-chair.programs'],
                ['Users', 'groups', 'department-chair.teachers', ['department-chair.teachers', 'department-chair.students*']],
                ['Subjects', 'menu_book', 'department-chair.subjects'],
            ]);
        }
        if ($items === []) {
            return self::items([
                ['Dashboard', 'dashboard', 'student.dashboard'],
                ['Classes', 'school', 'student.classes'],
                ['Assessments', 'assignment', 'student.assessments'],
                ['Results', 'grading', 'student.results'],
            ]);
        }

        $dashboard = $user->hasRole('admin_dean') ? 'admin-dean.dashboard'
            : ($user->hasRole('department_chair') ? 'department-chair.dashboard' : 'instructor.dashboard');
        array_unshift($items, ['Dashboard', 'dashboard', $dashboard, [
            'instructor.dashboard', 'admin-dean.dashboard', 'department-chair.dashboard',
        ]]);

        return self::items($items);
    }

    private static function items(array $items): array
    {
        return array_map(function (array $item): array {
            [$label, $icon, $route] = $item;

            return [
                'label' => $label,
                'icon' => $icon,
                'href' => route($route),
                'active' => request()->routeIs(...(array) ($item[3] ?? $route.'*')),
            ];
        }, $items);
    }
}
