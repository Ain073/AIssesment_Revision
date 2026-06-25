<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedDepartmentId = $scopedDepartment?->department_id;
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $scopedProgramIds = $scopedPrograms->pluck('program_id');
        $teacherQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            );

        return view('department-chair.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Teachers',
                    'value' => (clone $teacherQuery)->count(),
                    'caption' => 'Instructor profiles in your department',
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => $scopedProgramIds->isNotEmpty()
                        ? StudentProfile::query()->whereIn('program_id', $scopedProgramIds)->count()
                        : 0,
                    'caption' => 'Student accounts under your current program scope',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Reports',
                    'value' => 0,
                    'caption' => 'Finalized instructor reports to monitor',
                    'icon' => 'summarize',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'View Teachers',
                    'description' => 'Check instructor records under your department.',
                    'href' => route('department-chair.teachers'),
                    'icon' => 'badge',
                ],
                [
                    'label' => 'View Students',
                    'description' => 'Check student records under your current program scope.',
                    'href' => route('department-chair.students'),
                    'icon' => 'groups',
                ],
                [
                    'label' => 'View Reports',
                    'description' => 'Monitor finalized instructor reports.',
                    'href' => route('department-chair.reports'),
                    'icon' => 'summarize',
                ],
            ],
        ]);
    }
}
