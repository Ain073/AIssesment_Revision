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

        if ($item->item_type === 'enumeration') {
            return self::enumerationScore($item, $answer);
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

        if ($item->item_type === 'enumeration') {
            $totalCorrect = $item->choices->where('is_correct', true)->count();
            $maxPoints = (float) max($totalCorrect, (float) $item->points);

            return $maxPoints > 0 && self::enumerationScore($item, $answer) >= $maxPoints;
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

    /**
     * Score an enumeration answer: each correct box earns 1 point.
     * Order-sensitive mode matches by position;
     * order-independent mode matches by pool membership.
     * Comparison is case-insensitive and trims whitespace.
     */
    public static function enumerationScore(AssessmentItem $item, ?SubmissionAnswer $answer): float
    {
        if (! $answer) {
            return 0.0;
        }

        $correctChoices = $item->choices
            ->where('is_correct', true)
            ->sortBy('sort_order')
            ->values();

        $totalCorrect = $correctChoices->count();

        if ($totalCorrect === 0) {
            return 0.0;
        }

        $pointsPerAnswer = 1.0;
        $maxPoints = (float) max($totalCorrect, (float) $item->points);

        // Student answers are stored as JSON array in answer_text
        $rawStudentAnswers = json_decode((string) $answer->answer_text, true);

        if (! is_array($rawStudentAnswers)) {
            // Fallback: single answer stored as plain text
            $rawStudentAnswers = [trim((string) $answer->answer_text)];
        }

        $studentAnswers = collect($rawStudentAnswers)
            ->map(fn ($a) => trim((string) $a))
            ->values();

        $earned = 0.0;

        if ($item->order_sensitive) {
            // Order-sensitive: slot-by-slot comparison
            foreach ($correctChoices as $index => $correctChoice) {
                $studentSlot = $studentAnswers->get($index, '');

                if ($studentSlot !== '' && strcasecmp(trim($studentSlot), trim((string) $correctChoice->choice_text)) === 0) {
                    $earned += $pointsPerAnswer;
                }
            }
        } else {
            // Order-independent: consume pool of correct answers to prevent duplicate credit
            $remainingCorrect = $correctChoices->pluck('choice_text')->map(fn ($c) => trim((string) $c))->values()->toArray();

            foreach ($studentAnswers as $studentSlot) {
                if ($studentSlot === '') {
                    continue;
                }

                $matchIndex = false;
                foreach ($remainingCorrect as $rIdx => $rVal) {
                    if (strcasecmp(trim($studentSlot), $rVal) === 0) {
                        $matchIndex = $rIdx;
                        break;
                    }
                }

                if ($matchIndex !== false) {
                    $earned += $pointsPerAnswer;
                    array_splice($remainingCorrect, $matchIndex, 1);
                }
            }
        }

        return self::clampPoints(round($earned, 2), $maxPoints);
    }

    public static function clampPoints(float $value, float $max): float
    {
        return max(0.0, min($value, $max));
    }
}
