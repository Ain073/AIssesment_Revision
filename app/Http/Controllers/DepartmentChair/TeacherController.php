<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\StudentProfile;
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
        $scopedProgramIds = $this->scopedPrograms($scopedDepartment)->pluck('program_id');
        $studentsCount = $scopedProgramIds->isNotEmpty()
            ? StudentProfile::query()->whereIn('program_id', $scopedProgramIds)->count()
            : 0;

        return view('department-chair.teachers.index', $this->sharedData($user, 'teachers') + [
            'teachers' => $teachers,
            'studentsCount' => $studentsCount,
            'scopedDepartment' => $scopedDepartment,
        ]);
    }
}
