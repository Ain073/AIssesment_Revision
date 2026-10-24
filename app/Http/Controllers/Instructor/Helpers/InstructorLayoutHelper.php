<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\InstructorProfile;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait InstructorLayoutHelper
{
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
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    protected function instructorProfile(User $user): ?InstructorProfile
    {
        return $user->instructorProfile;
    }
}
