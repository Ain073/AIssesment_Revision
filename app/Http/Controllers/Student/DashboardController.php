<?php

namespace App\Http\Controllers\Student;

use App\Models\AcademicClass;
use App\Models\AssessmentItem;
use App\Models\ClassAssessment;
use App\Models\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $studentProfile = $user->studentProfile;
        $classes = $studentProfile
            ? $this->activeClasses($studentProfile->student_profile_id)
            : collect();
        $classIds = $classes->pluck('class_id');
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

    private function activeClasses(int $studentProfileId): Collection
    {
        return AcademicClass::query()
            ->with([
                'subject',
                'instructorProfile.user',
                'classAssessments' => function ($query) use ($studentProfileId): void {
                    $query
                        ->where('publish_status', ClassAssessment::STATUS_PUBLISHED)
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
            ->whereNull('archived_at')
            ->whereHas('students', fn ($query) => $query->where('student_profiles.student_profile_id', $studentProfileId))
            ->orderBy('class_name')
            ->get();
    }

    private function classPerformance(Collection $classes): Collection
    {
        return $classes->map(function (AcademicClass $class): array {
            $releasedResults = $class->classAssessments
                ->where('score_visibility', true)
                ->map(fn (ClassAssessment $classAssessment) => $this->bestResult($classAssessment))
                ->filter();
            $average = $releasedResults->isNotEmpty()
                ? round((float) $releasedResults->avg('percentage'), 1)
                : null;

            return [
                'class' => $class,
                'subject' => $class->subject,
                'instructor' => $class->instructorProfile?->user,
                'assigned_count' => $class->classAssessments->count(),
                'released_count' => $releasedResults->count(),
                'passed_count' => $releasedResults->where('passed', true)->count(),
                'failed_count' => $releasedResults->where('passed', false)->count(),
                'average_percentage' => $average,
            ];
        });
    }

    private function bestResult(ClassAssessment $classAssessment): ?array
    {
        $assessment = $classAssessment->assessment;
        $items = $assessment?->items ?? collect();
        $maxScore = (float) $items->sum(fn (AssessmentItem $item) => (float) $item->points);
        $passingScore = $maxScore * 0.75;
        $submittedAttempts = $classAssessment->submissions
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
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function (AssessmentItem $item) use ($answers): float {
            $answer = $answers->get($item->assessment_item_id);

            return $answer && $this->isCorrectAnswer($item, $answer)
                ? (float) $item->points
                : 0.0;
        });
    }

    private function isCorrectAnswer(AssessmentItem $item, $answer): bool
    {
        if ($answer->choice) {
            return (bool) $answer->choice->is_correct;
        }

        $correctAnswers = $item->choices
            ->where('is_correct', true)
            ->pluck('choice_text')
            ->map(fn ($choice): string => Str::lower(trim((string) $choice)))
            ->filter();
        $studentAnswer = Str::lower(trim((string) $answer->answer_text));

        return $studentAnswer !== '' && $correctAnswers->contains($studentAnswer);
    }
}
