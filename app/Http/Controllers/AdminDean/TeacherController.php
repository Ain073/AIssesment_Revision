<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends BaseController
{
    public function index(Request $request): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;
        $departments = Department::query()
            ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('dept_name')
            ->get();
        $selectedDepartment = $departments->firstWhere('public_id', $request->query('department'));
        $selectedDepartmentId = $selectedDepartment?->department_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query
                    ->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
                    ->when($selectedDepartmentId, fn ($inner) => $inner->where('department_id', $selectedDepartmentId))
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        return view('admin-dean.teachers.index', $this->sharedData('teachers') + [
            'teachers' => $teachers,
            'scopedCollege' => $scopedCollege,
            'departments' => $departments,
            'selectedDepartment' => $selectedDepartment,
            'selectedDepartmentKey' => $selectedDepartment?->public_id,
        ]);
    }
}
