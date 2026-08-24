<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.dashboard.index', $this->sharedData('dashboard') + [
            'user' => $user,
            'stats' => [
                [
                    'label' => 'Departments',
                    'value' => Department::query()
                        ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                        ->count(),
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Programs',
                    'value' => Program::query()
                        ->when($scopedCollegeId, fn ($query) => $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('college_id', $scopedCollegeId)), fn ($query) => $query->whereRaw('1 = 0'))
                        ->count(),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Teachers',
                    'value' => User::query()
                        ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
                        ->whereHas(
                            'instructorProfile.department',
                            fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
                        )
                        ->count(),
                    'icon' => 'badge',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Departments',
                    'href' => route('admin-dean.departments'),
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'View Teachers',
                    'href' => route('admin-dean.teachers'),
                    'icon' => 'badge',
                ],
            ],
        ]);
    }
}
