<?php

namespace App\Services;

use App\Models\AssessmentItem;
use App\Models\AssessmentItemChoice;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Support\AssessmentScoring;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptedAnswerCorrection
{
    public function preview(AssessmentItem $item, string $alternative): array
    {
        $alternative = trim($alternative);
        $accepted = $item->choices->where('is_correct', true)->pluck('choice_text')->values();

        if ($alternative === '' || $accepted->contains(fn ($text): bool => Str::lower(trim($text)) === Str::lower($alternative))) {
            throw ValidationException::withMessages(['alternative_answer' => 'Enter a new alternative that is not already accepted.']);
        }

        if ($accepted->count() >= 10) {
            throw ValidationException::withMessages(['alternative_answer' => 'This question already has the maximum of 10 accepted answers.']);
        }

        $proposedItem = clone $item;
        $proposedItem->setRelation('choices', $item->choices->concat([
            new AssessmentItemChoice(['choice_text' => $alternative, 'is_correct' => true]),
        ]));

        // Include every publication sharing this question, including older shared records.
        $answers = SubmissionAnswer::query()
            ->where('assessment_item_id', $item->getKey())
            ->whereHas('submission', fn ($query) => $query
                ->where('status', Submission::STATUS_SUBMITTED)
                ->whereHas('publishAssessment', fn ($published) => $published->where('assessment_id', $item->assessment_id)))
            ->with(['choice', 'submission.studentProfile.user'])
            ->orderBy('submission_answer_id')
            ->get();

        $changes = $answers->map(function (SubmissionAnswer $answer) use ($item, $proposedItem): ?array {
            $before = AssessmentScoring::earnedPoints($item, $answer);
            $after = AssessmentScoring::earnedPoints($proposedItem, $answer);

            if ($before === $after) {
                return null;
            }

            return [
                'answer_id' => $answer->getKey(),
                'student_id' => $answer->submission->student_profile_id,
                'student_name' => $answer->submission->studentProfile?->user?->displayName() ?? 'Student account',
                'student_number' => $answer->submission->studentProfile?->student_number,
                'attempt_number' => $answer->submission->attempt_number,
                'answer_text' => $answer->answer_text,
                'before' => $before,
                'after' => $after,
            ];
        })->filter()->values();

        return [
            'alternative' => $alternative,
            'accepted' => $accepted->all(),
            'changes' => $changes,
            'attempts_count' => $changes->count(),
            'students_count' => $changes->pluck('student_id')->unique()->count(),
            'key_revision' => hash('sha256', serialize([
                $item->question_text, $item->item_type, $item->points,
                $item->choices->map(fn ($choice): array => [
                    $choice->getKey(), $choice->choice_text, $choice->is_correct, $choice->sort_order,
                ])->all(),
            ])),
            'impact_revision' => hash('sha256', serialize($changes->map(fn (array $change): array => [
                $change['answer_id'], $change['student_id'], $change['answer_text'], $change['before'], $change['after'],
            ])->all())),
        ];
    }
}
