<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\User;
use Illuminate\View\View;

class TeacherController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedDepartmentId = $scopedDepartment?->department_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        return view('department-chair.teachers', $this->sharedData($user, 'teachers') + [
            'teachers' => $teachers,
            'scopedDepartment' => $scopedDepartment,
        ]);
    }
}
