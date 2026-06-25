<?php

namespace App\Http\Controllers\AdminDean;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

abstract class BaseController extends Controller
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

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    protected function scopedCollege(User $user): ?College
    {
        $user->loadMissing('instructorProfile.department.college');

        return $user->instructorProfile?->department?->college;
    }

    protected function studentImportScope(?College $college): string
    {
        return 'college:'.(int) $college?->college_id;
    }

    protected function buildName(array $validated): string
    {
        return collect([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ])->filter()->implode(' ');
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
