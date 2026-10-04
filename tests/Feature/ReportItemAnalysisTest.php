<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\AssessmentItemChoice;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Support\ReportItemAnalysis;
use Tests\TestCase;

class ReportItemAnalysisTest extends TestCase
{
    public function test_counts_use_one_best_attempt_per_student_and_exclude_ineligible_attempts(): void
    {
        $items = collect([$this->item(1), $this->item(2)]);
        $submissions = collect([
            $this->submission(1, 1, [$this->answer(1, 'wrong'), $this->answer(2, 'wrong')]),
            $this->submission(1, 2, [$this->answer(1, 'yes'), $this->answer(2, 'yes')]),
            $this->submission(1, 3, [$this->answer(1, 'wrong'), $this->answer(2, 'yes')]),
            $this->submission(2, 1, [$this->answer(1, 'wrong'), $this->answer(2, ' ')]),
            $this->submission(3, 1, [$this->answer(1, 'yes')], Submission::STATUS_IN_PROGRESS),
            $this->submission(4, 1, [$this->answer(1, 'yes')], Submission::STATUS_SUBMITTED, Submission::COMPLETION_WARNING_LIMIT),
        ]);

        $summary = collect(ReportItemAnalysis::summarize($items, $submissions));

        $this->assertSame(2, $summary[0]['response_count']);
        $this->assertSame(1, $summary[0]['correct_count']);
        $this->assertSame(1, $summary[0]['incorrect_count']);
        $this->assertSame(1, $summary[1]['unanswered_count']);
        $this->assertSame(50.0, $summary[0]['correct_rate']);
        $this->assertSame([1, 2], array_column(ReportItemAnalysis::strongestItems($summary), 'item_number'));
        $this->assertSame([1, 2], array_column(ReportItemAnalysis::weakestItems($summary), 'item_number'));
    }

    public function test_equal_score_attempts_use_the_later_attempt_consistently(): void
    {
        $items = collect([$this->item(1), $this->item(2)]);
        $submissions = collect([
            $this->submission(1, 1, [$this->answer(1, 'yes'), $this->answer(2, 'wrong')]),
            $this->submission(1, 2, [$this->answer(1, 'wrong'), $this->answer(2, 'yes')]),
        ]);

        $summary = ReportItemAnalysis::summarize($items, $submissions);

        $this->assertSame(0, $summary[0]['correct_count']);
        $this->assertSame(1, $summary[1]['correct_count']);
    }

    public function test_blank_enumerations_and_pending_essays_are_not_incorrect(): void
    {
        $items = collect([$this->item(1, 'enumeration'), $this->item(2, 'essay'), $this->item(3)]);
        $submissions = collect([
            $this->submission(1, 1, [$this->answer(1, '["", " "]'), $this->answer(2, 'Essay response'), $this->answer(3, 'yes')]),
            $this->submission(2, 1, [$this->answer(1, '["yes"]'), $this->answer(2, ' ')]),
        ]);

        $summary = collect(ReportItemAnalysis::summarize($items, $submissions));

        $this->assertSame(1, $summary[0]['unanswered_count']);
        $this->assertSame(0, $summary[0]['incorrect_count']);
        $this->assertSame(1, $summary[1]['pending_count']);
        $this->assertSame(1, $summary[1]['unanswered_count']);
        $this->assertSame(0, $summary[1]['incorrect_count']);
        $this->assertNull($summary[1]['correct_rate']);
        $this->assertSame(1, $summary[2]['unanswered_count']);
        $this->assertSame([1, 3], array_column(ReportItemAnalysis::weakestItems($summary), 'item_number'));
    }

    public function test_all_items_and_all_ties_are_retained_without_takers_claiming_mastery(): void
    {
        $items = collect(range(1, 5))->map(fn (int $number): AssessmentItem => $this->item($number));
        $empty = collect(ReportItemAnalysis::summarize($items, collect()));

        $this->assertCount(5, $empty);
        $this->assertNull($empty[0]['correct_rate']);
        $this->assertSame([], ReportItemAnalysis::strongestItems($empty));
        $this->assertSame([], ReportItemAnalysis::weakestItems($empty));

        $allCorrect = collect(ReportItemAnalysis::summarize($items, collect([
            $this->submission(1, 1, $items->map(fn ($item) => $this->answer($item->sort_order, 'yes'))->all()),
        ])));

        $this->assertCount(5, ReportItemAnalysis::strongestItems($allCorrect));
        $this->assertCount(5, ReportItemAnalysis::weakestItems($allCorrect));
    }

    public function test_choice_answers_and_graded_partial_essays_use_existing_scoring(): void
    {
        $choiceItem = $this->item(1, 'multiple_choice');
        $choiceAnswer = $this->answer(1, null);
        $choiceAnswer->assessment_item_choice_id = 10;
        $choiceAnswer->setRelation('choice', $choiceItem->choices->first());
        $essayAnswer = $this->answer(2, 'Partially correct essay');
        $essayAnswer->earned_points = 0.5;

        $summary = ReportItemAnalysis::summarize(collect([$choiceItem, $this->item(2, 'essay')]), collect([
            $this->submission(1, 1, [$choiceAnswer, $essayAnswer]),
        ]));

        $this->assertSame(1, $summary[0]['correct_count']);
        $this->assertSame(0, $summary[0]['unanswered_count']);
        $this->assertSame(1, $summary[1]['incorrect_count']);
        $this->assertSame(0, $summary[1]['pending_count']);
    }

    private function item(int $number, string $type = 'identification'): AssessmentItem
    {
        $item = new AssessmentItem([
            'sort_order' => $number, 'item_type' => $type, 'question_text' => 'Question '.$number, 'points' => 1,
        ]);
        $item->assessment_item_id = $number;
        $item->setRelation('choices', collect([new AssessmentItemChoice(['choice_text' => 'yes', 'is_correct' => true])]));

        return $item;
    }

    private function answer(int $number, ?string $text): SubmissionAnswer
    {
        $answer = new SubmissionAnswer(['assessment_item_id' => $number, 'answer_text' => $text]);
        $answer->setRelation('choice', null);

        return $answer;
    }

    private function submission(int $student, int $attempt, array $answers, string $status = Submission::STATUS_SUBMITTED, ?string $reason = null): Submission
    {
        $submission = new Submission([
            'student_profile_id' => $student, 'attempt_number' => $attempt, 'status' => $status, 'completion_reason' => $reason,
        ]);
        $submission->setRelation('answers', collect($answers));

        return $submission;
    }
}
