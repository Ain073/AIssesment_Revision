<?php

namespace App\Http\Controllers\Instructor;

use App\Models\AcademicClass;
use App\Models\PublishAssessment;
use App\Models\ClassDetail;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): RedirectResponse|View
    {
        $user = $this->currentUser();

        if ($user->hasRole('admin_dean')) {
            return redirect()->route('admin-dean.dashboard');
        }

        if ($user->hasRole('department_chair')) {
            return redirect()->route('department-chair.dashboard');
        }

        $instructorProfile = $this->instructorProfile($user);
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->whereNull('classes.archived_at')
                ->with([
                    'contextDetail',
                    'subject',
                    'students',
                    'classDetails.studentProfile.user',
                    'publishAssessments' => fn ($query) => $query
                        ->where(function ($statusQuery): void {
                            $statusQuery->where('publish_status', PublishAssessment::STATUS_CLOSED)
                                ->orWhere(function ($dueQuery): void {
                                    $dueQuery->whereNotNull('due_at')
                                        ->where('due_at', '<=', now());
                                });
                        })
                        ->with([
                            'assessment.items.choices',
                            'submissions' => fn ($submissionQuery) => $submissionQuery
                                ->where('status', Submission::STATUS_SUBMITTED)
                                ->with('answers.choice'),
                        ]),
                ])
                ->latest('class_id')
                ->get()
                ->each(fn (AcademicClass $class) => $class->applyEnrolledStudentsCount())
            : collect();
        $classIds = $classes->pluck('class_id');
        $classesCount = $classes->count();
        $studentsCount = $classIds->isNotEmpty()
            ? DB::table('class_details')
                ->whereIn('class_id', $classIds)
                ->whereNotNull('student_id')
                ->where('status', ClassDetail::STATUS_APPROVED)
                ->distinct()
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->count()
            : 0;
        $assessmentsCount = $instructorProfile?->assessments()->count() ?? 0;

        return view('instructor.dashboard.index', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Classes Handled',
                    'value' => $classesCount,
                    'icon' => 'school',
                ],
                [
                    'label' => 'Students',
                    'value' => $studentsCount,
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Assessments',
                    'value' => $assessmentsCount,
                    'icon' => 'assignment',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Classes',
                    'href' => route('instructor.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Create Assessment',
                    'href' => route('instructor.assessments.create'),
                    'icon' => 'assignment_add',
                ],
                [
                    'label' => 'Prepare Reports',
                    'href' => route('instructor.reports'),
                    'icon' => 'summarize',
                ],
            ],
            'activeClassPerformance' => $classes
                ->map(fn (AcademicClass $class): array => $this->classPerformanceSummary($class)),
        ]);
    }
}
