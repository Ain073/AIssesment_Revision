<?php

namespace App\Http\Controllers\Student\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait StudentLayoutHelper
{
    protected function placeholderPageData(
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

    protected function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Student Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Student Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => [],
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search classes or assessments...',
        ];
    }

    protected function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments')],
            ['key' => 'results', 'label' => 'Results', 'icon' => 'grading', 'href' => route('student.results')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'studentProfile.program.college');

        return $user;
    }
}
