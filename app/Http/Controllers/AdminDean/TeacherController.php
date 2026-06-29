<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\User;
use Illuminate\View\View;

class TeacherController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        return view('admin-dean.teachers', $this->sharedData('teachers') + [
            'teachers' => $teachers,
            'scopedCollege' => $scopedCollege,
            'departments' => Department::query()
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('dept_name')
                ->get(),
        ]);
    }
}
