<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassDetail;
use App\Models\Department;
use App\Models\Program;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;
        $teachingSummary = $this->teachingSummary($user);

        return view('admin-dean.dashboard.index', $this->sharedData('dashboard') + [
            'user' => $user,
            'scopedCollege' => $scopedCollege,
            'stats' => $this->collegeStats($scopedCollegeId),
            'departmentRows' => $this->departmentRows($scopedCollegeId),
            'teachingStats' => $teachingSummary['stats'],
            'teachingClasses' => $teachingSummary['classes'],
            'quickActions' => [
                [
                    'label' => 'Departments',
                    'href' => route('admin-dean.departments'),
                    'icon' => 'apartment',
                ],
                [
                    'label' => 'Users',
                    'href' => route('admin-dean.teachers'),
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Chair Designation',
                    'href' => route('admin-dean.designations'),
                    'icon' => 'admin_panel_settings',
                ],
            ],
        ]);
    }

    private function collegeStats(?int $collegeId): array
    {
        return [
            [
                'label' => 'Departments',
                'value' => Department::query()
                    ->when($collegeId, fn ($query) => $query->where('college_id', $collegeId), fn ($query) => $query->whereRaw('1 = 0'))
                    ->count(),
                'icon' => 'apartment',
            ],
            [
                'label' => 'Programs',
                'value' => Program::query()
                    ->when($collegeId, fn ($query) => $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('college_id', $collegeId)), fn ($query) => $query->whereRaw('1 = 0'))
                    ->count(),
                'icon' => 'school',
            ],
            [
                'label' => 'Teachers',
                'value' => $this->collegeTeacherQuery($collegeId)->count(),
                'icon' => 'badge',
            ],
            [
                'label' => 'Chair Designations',
                'value' => $this->collegeTeacherQuery($collegeId)
                    ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
                    ->count(),
                'icon' => 'admin_panel_settings',
            ],
        ];
    }

    private function departmentRows(?int $collegeId): Collection
    {
        if (! $collegeId) {
            return collect();
        }

        $chairsByDepartment = $this->collegeTeacherQuery($collegeId)
            ->with('instructorProfile.department')
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
            ->get()
            ->groupBy(fn (User $teacher) => $teacher->instructorProfile?->department_id);

        return Department::query()
            ->withCount(['programs', 'instructorProfiles as teachers_count'])
            ->where('college_id', $collegeId)
            ->orderBy('dept_name')
            ->get()
            ->map(fn (Department $department): array => [
                'department' => $department,
                'programs_count' => $department->programs_count,
                'teachers_count' => $department->teachers_count,
                'chairs' => $chairsByDepartment->get($department->department_id, collect()),
            ]);
    }

    private function teachingSummary(User $user): array
    {
        $instructorProfile = $user->instructorProfile;

        if (! $instructorProfile) {
            return [
                'stats' => collect(),
                'classes' => collect(),
            ];
        }

        $activeClasses = $instructorProfile->classes()
            ->with('subject')
            ->withCount('publishAssessments')
            ->whereNull('classes.archived_at')
            ->latest('classes.class_id')
            ->get()
            ->unique('class_id')
            ->values();
        $classIds = $activeClasses->pluck('class_id');
        $studentCount = $classIds->isNotEmpty()
            ? ClassDetail::query()
                ->whereIn('class_id', $classIds)
                ->whereNotNull('student_id')
                ->where('status', ClassDetail::STATUS_APPROVED)
                ->distinct('student_id')
                ->count('student_id')
            : 0;
        $submittedCount = $classIds->isNotEmpty()
            ? Submission::query()
                ->whereHas('publishAssessment.classDetail', fn ($query) => $query->whereIn('class_id', $classIds))
                ->where('status', Submission::STATUS_SUBMITTED)
                ->count()
            : 0;

        return [
            'stats' => collect([
                [
                    'label' => 'Active Classes',
                    'value' => $activeClasses->count(),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Class Students',
                    'value' => $studentCount,
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Drafts',
                    'value' => $instructorProfile->assessments()
                        ->where('status', '!=', Assessment::STATUS_ARCHIVED)
                        ->count(),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Submissions',
                    'value' => $submittedCount,
                    'icon' => 'task_alt',
                ],
            ]),
            'classes' => $activeClasses
                ->take(4)
                ->map(fn (AcademicClass $class): array => [
                    'class' => $class,
                    'published_count' => (int) ($class->publish_assessments_count ?? 0),
                ]),
        ];
    }

    private function collegeTeacherQuery(?int $collegeId)
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->when($collegeId, fn ($inner) => $inner->where('college_id', $collegeId), fn ($inner) => $inner->whereRaw('1 = 0'))
            );
    }
}
