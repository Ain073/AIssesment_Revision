<?php

namespace App\Support;

use App\Models\AssessmentItem;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AssessmentScoring
{
    public static function scoreSubmission(Submission $submission, Collection $items): float
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function (AssessmentItem $item) use ($answers): float {
            return self::earnedPoints($item, $answers->get($item->assessment_item_id));
        });
    }

    public static function earnedPoints(AssessmentItem $item, ?SubmissionAnswer $answer): float
    {
        if (! $answer) {
            return 0.0;
        }

        if ($item->item_type === 'essay') {
            if ($answer->earned_points === null) {
                return 0.0;
            }

            return self::clampPoints((float) $answer->earned_points, (float) $item->points);
        }

        return self::isCorrect($item, $answer)
            ? (float) $item->points
            : 0.0;
    }

    public static function isCorrect(AssessmentItem $item, ?SubmissionAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        if ($item->item_type === 'essay') {
            return $answer->earned_points !== null
                && self::clampPoints((float) $answer->earned_points, (float) $item->points) >= (float) $item->points;
        }

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

    public static function essayPendingCount(Submission $submission, Collection $items): int
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return $items
            ->where('item_type', 'essay')
            ->filter(function (AssessmentItem $item) use ($answers): bool {
                $answer = $answers->get($item->assessment_item_id);

                return $answer && $answer->earned_points === null;
            })
            ->count();
    }

    public static function clampPoints(float $value, float $max): float
    {
        return max(0.0, min($value, $max));
    }
}
