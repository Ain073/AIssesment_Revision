<?php

namespace App\Http\Controllers\Student;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResultController extends BaseController
{
    public function results(Request $request): View
    {
        $user = $this->currentUser();
        $studentProfile = $user->studentProfile;
        $submissions = $studentProfile
            ? Submission::query()
                ->with([
                    'answers.choice',
                    'classAssessment.assessment.items.choices',
                    'classAssessment.assessment.subject',
                    'classAssessment.class.subject',
                    'classAssessment.class.instructorProfile.user',
                ])
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_SUBMITTED)
                ->latest('submitted_at')
                ->get()
            : collect();
        $allResults = $this->buildResults($submissions);
        $classOptions = $this->classOptions($allResults);
        $selectedClassKey = (string) $request->query('class', '');

        if (! $classOptions->contains('key', $selectedClassKey)) {
            $selectedClassKey = '';
        }

        $results = $selectedClassKey !== ''
            ? $allResults
                ->where('class_key', $selectedClassKey)
                ->values()
            : $allResults;
        $releasedResults = $results->where('score_visible', true);

        return view('student.results.index', $this->sharedData($user, 'results') + [
            'studentProfile' => $studentProfile,
            'results' => $results,
            'classOptions' => $classOptions,
            'selectedClassKey' => $selectedClassKey,
            'selectedClassLabel' => $selectedClassKey !== ''
                ? $classOptions->firstWhere('key', $selectedClassKey)['label']
                : 'All Classes',
            'summary' => [
                'submitted_assessments' => $results->count(),
                'passed_count' => $releasedResults->where('passed', true)->count(),
                'failed_count' => $releasedResults->where('passed', false)->count(),
            ],
            'topbarSearchPlaceholder' => 'Search results...',
        ]);
    }

    private function buildResults(Collection $submissions): Collection
    {
        return $submissions
            ->groupBy('class_assessment_id')
            ->map(function (Collection $attempts): array {
                $classAssessment = $attempts->first()->classAssessment;
                $assessment = $classAssessment?->assessment;
                $items = $assessment?->items ?? collect();
                $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
                $passingScore = $maxScore * 0.75;
                $attemptRows = $attempts
                    ->sortBy('attempt_number')
                    ->map(function (Submission $submission) use ($items, $maxScore, $passingScore): array {
                        $score = $this->submissionScore($submission, $items);
                        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;

                        return [
                            'attempt_number' => $submission->attempt_number,
                            'score' => $score,
                            'score_text' => $this->formatNumber($score),
                            'percentage' => $percentage,
                            'passed' => $maxScore > 0 && $score >= $passingScore,
                            'submitted_at' => $submission->submitted_at,
                            'completion_reason' => $submission->completion_reason,
                            'warning_count' => (int) $submission->warning_count,
                        ];
                    })
                    ->values();
                $bestAttempt = $attemptRows
                    ->sortByDesc('score')
                    ->first();

                return [
                    'class_assessment' => $classAssessment,
                    'assessment' => $assessment,
                    'class' => $classAssessment?->class,
                    'class_key' => $classAssessment?->class?->public_id,
                    'best_attempt' => $bestAttempt,
                    'attempts' => $attemptRows,
                    'attempt_count' => $attemptRows->count(),
                    'max_score' => $maxScore,
                    'max_score_text' => $this->formatNumber($maxScore),
                    'score_visible' => (bool) $classAssessment?->score_visibility,
                    'answer_visible' => (bool) $classAssessment?->answer_visibility,
                    'percentage' => $bestAttempt['percentage'] ?? 0,
                    'passed' => $bestAttempt['passed'] ?? false,
                    'latest_submitted_at' => $attemptRows->pluck('submitted_at')->filter()->max(),
                ];
            })
            ->sortByDesc(fn (array $result): int => $result['latest_submitted_at']?->timestamp ?? 0)
            ->values();
    }

    private function classOptions(Collection $results): Collection
    {
        return $results
            ->pluck('class')
            ->filter()
            ->unique('class_id')
            ->map(fn ($class): array => [
                'key' => $class->public_id,
                'label' => $class->class_name,
                'subject' => $class->subject?->subject_code ?? 'No subject',
            ])
            ->values();
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function ($item) use ($answers): float {
            $answer = $answers->get($item->assessment_item_id);

            return $answer && $this->isCorrectAnswer($item, $answer)
                ? (float) $item->points
                : 0.0;
        });
    }

    private function isCorrectAnswer($item, $answer): bool
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

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
