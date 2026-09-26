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
        $reportCounts = $this->finalizedReportCounts($scopedDepartmentId);
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
                    'value' => $scopedDepartmentId
                        ? Subject::query()
                            ->where(function ($query) use ($scopedDepartmentId, $scopedProgramIds): void {
                                $query->where('department_id', $scopedDepartmentId);

                                if ($scopedProgramIds->isNotEmpty()) {
                                    $query->orWhereIn('program_id', $scopedProgramIds);
                                }
                            })
                            ->count()
                        : 0,
                    'icon' => 'menu_book',
                ],
                [
                    'label' => 'Formative Reports',
                    'value' => $reportCounts[Report::TYPE_FORMATIVE] ?? 0,
                    'icon' => 'summarize',
                ],
                [
                    'label' => 'Summative Reports',
                    'value' => $reportCounts[Report::TYPE_SUMMATIVE] ?? 0,
                    'icon' => 'summarize',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Add Program',
                    'href' => route('department-chair.programs', ['action' => 'create-program']),
                    'icon' => 'add',
                ],
                [
                    'label' => 'Add Teacher',
                    'href' => route('department-chair.teachers', ['tab' => 'teachers', 'action' => 'create-teacher']),
                    'icon' => 'person_add',
                ],
                [
                    'label' => 'Add Subject',
                    'href' => route('department-chair.subjects', ['action' => 'create-subject']),
                    'icon' => 'library_add',
                ],
            ],
        ]);
    }

    private function programRows(Collection $programs): Collection
    {
        $programIds = $programs->pluck('program_id');
        return $programs->map(fn ($program): array => [
            'program' => $program,
            'students_count' => $program->student_profiles_count,
            'classes_count' => AcademicClass::query()
                ->where('program_id', $program->program_id)
                ->whereNull('archived_at')
                ->count(),
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

    private function finalizedReportCounts(?int $departmentId): Collection
    {
        if (! $departmentId) {
            return collect([
                Report::TYPE_FORMATIVE => 0,
                Report::TYPE_SUMMATIVE => 0,
            ]);
        }

        return Report::query()
            ->where('report_status', Report::STATUS_FINALIZED)
            ->whereHas('publishAssessment.assessment.instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->select('report_type', DB::raw('count(*) as reports_count'))
            ->groupBy('report_type')
            ->pluck('reports_count', 'report_type');
    }
}
