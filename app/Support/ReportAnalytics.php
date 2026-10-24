<?php

namespace App\Support;

use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use Illuminate\Support\Collection;

class ReportAnalytics
{
    public static function summarize(PublishAssessment $publishAssessment): array
    {
        $items = $publishAssessment->assessment?->items ?? collect();
        $submissions = self::reportableSubmissions($publishAssessment->submissions);
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $studentScores = $submissions
            ->groupBy('student_profile_id')
            ->map(function (Collection $studentSubmissions) use ($items): float {
                return (float) $studentSubmissions
                    ->map(fn (Submission $submission): float => AssessmentScoring::scoreSubmission($submission, $items))
                    ->max();
            })
            ->values();
        $passingScore = $maxScore > 0
            ? PassingRateSetting::passingScore($maxScore, $publishAssessment->class?->year_level)
            : 0;

        return [
            'students_count' => $publishAssessment->class?->enrolledStudentsCount() ?? 0,
            'takers_count' => $studentScores->count(),
            'item_count' => $items->count(),
            'highest_score' => $studentScores->isNotEmpty() ? self::formatNumber((float) $studentScores->max()) : '0',
            'lowest_score' => $studentScores->isNotEmpty() ? self::formatNumber((float) $studentScores->min()) : '0',
            'mean_score' => $studentScores->isNotEmpty() ? self::formatNumber((float) $studentScores->avg()) : '0',
            'mean_percentage' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(((float) $studentScores->avg() / $maxScore) * 100, 2)
                : 0,
            'passing_rate' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(($studentScores->filter(fn (float $score): bool => $score >= $passingScore)->count() / $studentScores->count()) * 100, 2)
                : 0,
            'max_score' => self::formatNumber($maxScore),
        ];
    }

    public static function reportableSubmissions(Collection $submissions): Collection
    {
        return $submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->reject(fn (Submission $submission): bool => $submission->completion_reason === Submission::COMPLETION_WARNING_LIMIT)
            ->values();
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
