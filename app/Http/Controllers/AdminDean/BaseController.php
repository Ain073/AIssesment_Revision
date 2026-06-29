<?php

namespace App\Http\Controllers\AdminDean;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AdminDean\Helpers\AdminDeanLayoutHelper;
use App\Models\College;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class BaseController extends Controller
{
    use AdminDeanLayoutHelper;

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

}
