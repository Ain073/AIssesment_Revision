<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassDetail;
use App\Models\Report;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $teachingSummary = $this->teachingSummary($user);
        $teacherQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            );

        return view('department-chair.dashboard.index', $this->sharedData($user, 'dashboard') + [
            'user' => $user,
            'scopedDepartment' => $scopedDepartment,
            'programRows' => $this->programRows($scopedPrograms),
            'teachingStats' => $teachingSummary['stats'],
            'teachingClasses' => $teachingSummary['classes'],
            'stats' => [
                [
                    'label' => 'Programs',
                    'value' => $scopedPrograms->count(),
                    'icon' => 'school',
                ],
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
                    'label' => 'Subjects',
                    'value' => $scopedProgramIds->isNotEmpty()
                        ? Subject::query()->whereIn('program_id', $scopedProgramIds)->count()
                        : 0,
                    'icon' => 'menu_book',
                ],
                [
                    'label' => 'Reports',
                    'value' => $this->finalizedReportsCount($scopedDepartmentId),
                    'icon' => 'summarize',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Add User',
                    'href' => route('department-chair.teachers', ['tab' => 'teachers', 'action' => 'create-teacher']),
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Add Subject',
                    'href' => route('department-chair.subjects', ['action' => 'create-subject']),
                    'icon' => 'menu_book',
                ],
                [
                    'label' => 'Reports',
                    'href' => route('department-chair.reports'),
                    'icon' => 'summarize',
                ],
            ],
        ]);
    }

    private function programRows(Collection $programs): Collection
    {
        $programIds = $programs->pluck('program_id');
        $subjectCounts = $programIds->isNotEmpty()
            ? Subject::query()
                ->whereIn('program_id', $programIds)
                ->select('program_id', DB::raw('count(*) as subjects_count'))
                ->groupBy('program_id')
                ->pluck('subjects_count', 'program_id')
            : collect();

        return $programs->map(fn ($program): array => [
            'program' => $program,
            'students_count' => $program->student_profiles_count,
            'subjects_count' => (int) ($subjectCounts[$program->program_id] ?? 0),
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

    private function finalizedReportsCount(?int $departmentId): int
    {
        if (! $departmentId) {
            return 0;
        }

        return Report::query()
            ->where('report_status', Report::STATUS_FINALIZED)
            ->whereHas('publishAssessment.assessment.instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->count();
    }
}
