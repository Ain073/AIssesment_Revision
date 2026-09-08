<?php

namespace App\Http\Controllers\Student;

use App\Models\AcademicClass;
use App\Models\AssessmentItem;
use App\Models\PublishAssessment;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $studentProfile = $user->studentProfile;
        $classes = $studentProfile
            ? $this->activeClasses($studentProfile)
            : collect();
        $classIds = $classes->pluck('class_id');
        $assignedAssessmentsCount = $classIds->isNotEmpty()
            ? PublishAssessment::query()
                ->whereHas('classDetail', fn ($query) => $query->whereIn('class_id', $classIds))
                ->where('publish_status', PublishAssessment::STATUS_PUBLISHED)
                ->count()
            : 0;
        $releasedResultsCount = $studentProfile
            ? Submission::query()
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_SUBMITTED)
                ->whereHas('publishAssessment', fn ($query) => $query->where('score_visibility', true))
                ->distinct()
                ->count('publish_assessment_id')
            : 0;

        return view('student.dashboard.index', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Enrolled Classes',
                    'value' => $classes->count(),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Assigned Assessments',
                    'value' => $assignedAssessmentsCount,
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Released Results',
                    'value' => $releasedResultsCount,
                    'icon' => 'monitoring',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Classes',
                    'href' => route('student.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Take Assessments',
                    'href' => route('student.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'View Results',
                    'href' => route('student.results'),
                    'icon' => 'grading',
                ],
            ],
            'classPerformance' => $studentProfile
                ? $this->classPerformance($classes)
                : collect(),
        ]);
    }

    private function activeClasses(StudentProfile $studentProfile): Collection
    {
        $studentProfileId = $studentProfile->student_profile_id;

        return $this->classesForStudent($studentProfile)
            ->with([
                'subject',
                'instructorProfile.user',
                'publishAssessments' => function ($query) use ($studentProfileId): void {
                    $query
                        ->where('publish_status', PublishAssessment::STATUS_PUBLISHED)
                        ->with([
                            'assessment.items.choices',
                            'submissions' => function ($submissionQuery) use ($studentProfileId): void {
                                $submissionQuery
                                    ->where('student_profile_id', $studentProfileId)
                                    ->where('status', Submission::STATUS_SUBMITTED)
                                    ->with('answers.choice');
                            },
                        ]);
                },
            ])
            ->whereNull('classes.archived_at')
            ->orderBy('classes.year_level')
            ->orderBy('classes.section_name')
            ->get();
    }

    private function classPerformance(Collection $classes): Collection
    {
        return $classes->map(function (AcademicClass $class): array {
            $releasedResults = $class->publishAssessments
                ->where('score_visibility', true)
                ->map(fn (PublishAssessment $publishAssessment) => $this->bestResult($publishAssessment))
                ->filter();
            $average = $releasedResults->isNotEmpty()
                ? round((float) $releasedResults->avg('percentage'), 1)
                : null;

            return [
                'class' => $class,
                'subject' => $class->subject,
                'instructor' => $class->instructorProfile?->user,
                'assigned_count' => $class->publishAssessments->count(),
                'released_count' => $releasedResults->count(),
                'passed_count' => $releasedResults->where('passed', true)->count(),
                'failed_count' => $releasedResults->where('passed', false)->count(),
                'average_percentage' => $average,
            ];
        });
    }

    private function bestResult(PublishAssessment $publishAssessment): ?array
    {
        $assessment = $publishAssessment->assessment;
        $items = $assessment?->items ?? collect();
        $maxScore = (float) $items->sum(fn (AssessmentItem $item) => (float) $item->points);
        $passingScore = $maxScore * 0.75;
        $submittedAttempts = $publishAssessment->submissions
            ->where('status', Submission::STATUS_SUBMITTED);

        if ($submittedAttempts->isEmpty()) {
            return null;
        }

        return $submittedAttempts
            ->map(function (Submission $submission) use ($items, $maxScore, $passingScore): array {
                $score = $this->submissionScore($submission, $items);
                $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;

                return [
                    'score' => $score,
                    'percentage' => $percentage,
                    'passed' => $maxScore > 0 && $score >= $passingScore,
                ];
            })
            ->sortByDesc('score')
            ->first();
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
    }
}
