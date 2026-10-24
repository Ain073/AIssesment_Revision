<?php

namespace App\Http\Controllers\AdminDean\Helpers;

use App\Support\PortalNavigation;
use Illuminate\Support\Str;

trait AdminDeanLayoutHelper
{
    protected function sharedData(string $activeNav): array
    {
        $user = $this->currentUser();

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
