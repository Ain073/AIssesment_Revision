<?php

namespace App\Support;

use App\Models\AssessmentItem;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Support\Collection;

class ReportItemAnalysis
{
    public static function bestSubmissions(Collection $submissions, Collection $items): Collection
    {
        return $submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->reject(fn (Submission $submission): bool => $submission->completion_reason === Submission::COMPLETION_WARNING_LIMIT)
            ->groupBy('student_profile_id')
            ->map(fn (Collection $attempts): Submission => $attempts->sort(fn (Submission $a, Submission $b): int => [
                AssessmentScoring::scoreSubmission($b, $items), (int) $b->attempt_number, (int) $b->getKey(),
            ] <=> [
                AssessmentScoring::scoreSubmission($a, $items), (int) $a->attempt_number, (int) $a->getKey(),
            ])->first())
            ->values();
    }

    public static function summarize(Collection $items, Collection $submissions): array
    {
        $answersByStudent = self::bestSubmissions($submissions, $items)
            ->map(fn (Submission $submission): Collection => $submission->answers->keyBy('assessment_item_id'));
        $total = $answersByStudent->count();

        return $items->sortBy('sort_order')->map(function (AssessmentItem $item) use ($answersByStudent, $total): array {
            $correct = 0;
            $incorrect = 0;
            $unanswered = 0;
            $pending = 0;

            foreach ($answersByStudent as $answers) {
                $answer = $answers->get($item->assessment_item_id);

                if (! self::hasAnswer($item, $answer)) {
                    $unanswered++;
                } elseif ($item->item_type === 'essay' && $answer->earned_points === null) {
                    $pending++;
                } elseif (AssessmentScoring::isCorrect($item, $answer)) {
                    $correct++;
                } else {
                    $incorrect++;
                }
            }

            return [
                'item_number' => (int) $item->sort_order,
                'item_type' => (string) $item->item_type,
                'question' => (string) $item->question_text,
                'correct_answer' => $item->choices->where('is_correct', true)->pluck('choice_text')
                    ->map(fn ($choice): string => trim((string) $choice))->filter()->implode(', '),
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
                'unanswered_count' => $unanswered,
                'pending_count' => $pending,
                'response_count' => $total,
                'correct_rate' => $total > 0 && $pending === 0 ? round(($correct / $total) * 100, 2) : null,
            ];
        })->values()->all();
    }

    public static function strongestItems(Collection $items): array
    {
        return self::extremeItems($items, true);
    }

    public static function weakestItems(Collection $items): array
    {
        return self::extremeItems($items, false);
    }

    private static function extremeItems(Collection $items, bool $highest): array
    {
        $gradedItems = $items->filter(fn (array $item): bool => $item['correct_rate'] !== null);

        if ($gradedItems->isEmpty()) {
            return [];
        }

        $rate = $highest ? $gradedItems->max('correct_rate') : $gradedItems->min('correct_rate');

        return $gradedItems->where('correct_rate', $rate)->values()->all();
    }

    private static function hasAnswer(AssessmentItem $item, ?SubmissionAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        if ($answer->assessment_item_choice_id !== null) {
            return true;
        }

        if ($item->item_type === 'enumeration') {
            $slots = json_decode((string) $answer->answer_text, true);

            if (is_array($slots)) {
                return collect($slots)->contains(fn ($slot): bool => trim((string) $slot) !== '');
            }
        }

        return trim((string) $answer->answer_text) !== '';
    }
}
