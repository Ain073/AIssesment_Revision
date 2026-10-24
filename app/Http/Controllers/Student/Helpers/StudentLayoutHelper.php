<?php

namespace App\Http\Controllers\Student\Helpers;

use App\Models\User;
use App\Support\PortalNavigation;
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
            'portalSubtitle' => $user->portalSubtitle(),
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => $user->portalProfileMeta(),
            'navItems' => PortalNavigation::forUser($user),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search classes or assessments...',
        ];
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'studentProfile.program.department.college');

        return $user;
    }
}
