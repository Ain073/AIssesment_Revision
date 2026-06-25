<?php

namespace App\Http\Controllers\Student;

use App\Models\ClassAssessment;
use App\Models\Submission;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $studentProfile = $user->studentProfile;
        $classIds = $studentProfile
            ? $studentProfile->classes()
                ->whereNull('classes.archived_at')
                ->pluck('classes.class_id')
            : collect();
        $assignedAssessmentsCount = $classIds->isNotEmpty()
            ? ClassAssessment::query()
                ->whereIn('class_id', $classIds)
                ->where('publish_status', ClassAssessment::STATUS_PUBLISHED)
                ->count()
            : 0;
        $releasedResultsCount = $studentProfile
            ? Submission::query()
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_SUBMITTED)
                ->whereHas('classAssessment', fn ($query) => $query->where('score_visibility', true))
                ->distinct()
                ->count('class_assessment_id')
            : 0;

        return view('student.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Enrolled Classes',
                    'value' => $classIds->count(),
                    'caption' => 'Classes linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Assigned Assessments',
                    'value' => $assignedAssessmentsCount,
                    'caption' => 'Assessments currently available',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Released Results',
                    'value' => $releasedResultsCount,
                    'caption' => 'Scores and published outcomes',
                    'icon' => 'monitoring',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Classes',
                    'description' => 'View your enrolled classes and sections.',
                    'href' => route('student.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Take Assessments',
                    'description' => 'Open the assessments assigned to you.',
                    'href' => route('student.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'View Results',
                    'description' => 'Check released scores and assessment outcomes.',
                    'href' => route('student.results'),
                    'icon' => 'grading',
                ],
            ],
        ]);
    }
}
