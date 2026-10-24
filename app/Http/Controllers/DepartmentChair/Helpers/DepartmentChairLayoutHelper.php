<?php

namespace App\Http\Controllers\DepartmentChair\Helpers;

use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Support\Str;

trait DepartmentChairLayoutHelper
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
}
