<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\Program;
use App\Models\StudentProfile;
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
                        ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
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
                [
                    'label' => 'Students',
                    'value' => StudentProfile::query()
                        ->whereHas('program', fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0')))
                        ->count(),
                    'icon' => 'groups',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Departments',
                    'href' => route('admin-dean.departments'),
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Manage Programs',
                    'href' => route('admin-dean.programs'),
                    'icon' => 'school',
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
