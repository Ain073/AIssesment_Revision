<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\StudentProfile;
use App\Models\Report;
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
        $reportsCount = $scopedDepartmentId
            ? Report::query()
                ->where('report_status', Report::STATUS_FINALIZED)
                ->whereHas('classAssessment.assessment.instructorProfile', fn ($query) => $query->where('department_id', $scopedDepartmentId))
                ->count()
            : 0;

        return view('department-chair.dashboard.index', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Teachers',
                    'value' => (clone $teacherQuery)->count(),
                    'icon' => 'badge',
                ],
                [
                    'label' => 'Students',
                    'value' => $scopedProgramIds->isNotEmpty()
                        ? StudentProfile::query()->whereIn('program_id', $scopedProgramIds)->count()
                        : 0,
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Reports',
                    'value' => $reportsCount,
                    'icon' => 'summarize',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'View Teachers',
                    'href' => route('department-chair.teachers'),
                    'icon' => 'badge',
                ],
                [
                    'label' => 'View Students',
                    'href' => route('department-chair.students'),
                    'icon' => 'groups',
                ],
                [
                    'label' => 'View Reports',
                    'href' => route('department-chair.reports'),
                    'icon' => 'summarize',
                ],
            ],
        ]);
    }
}
