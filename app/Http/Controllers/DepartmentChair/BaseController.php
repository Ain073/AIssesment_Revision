<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DepartmentChair\Helpers\DepartmentChairLayoutHelper;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

abstract class BaseController extends Controller
{
    use DepartmentChairLayoutHelper;

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    protected function scopedDepartment(User $user): ?Department
    {
        return $user->instructorProfile?->department;
    }

    /**
     * @return Collection<int, Program>
     */
    protected function scopedPrograms(?Department $department): Collection
    {
        if (! $department) {
            return collect();
        }

        return Program::query()
            ->with('college')
            ->where('college_id', $department->college_id)
            ->withCount('studentProfiles')
            ->orderBy('program_name')
            ->get();
    }

    protected function studentImportScope(?Department $department): string
    {
        return 'department:'.(int) $department?->department_id;
    }

}
