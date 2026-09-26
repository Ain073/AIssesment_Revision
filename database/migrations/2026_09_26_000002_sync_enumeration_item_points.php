<?php

use App\Models\AssessmentItem;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $items = AssessmentItem::where('item_type', 'enumeration')
            ->with('choices')
            ->get();

        foreach ($items as $item) {
            $correctCount = $item->choices->where('is_correct', true)->count();
            if ($correctCount > 0 && (float) $item->points !== (float) $correctCount) {
                $item->update(['points' => $correctCount]);
            }
        }

        // Recalculate existing submissions
        $submissions = Submission::with(['answers.item.choices', 'assessment.items.choices'])->get();
        foreach ($submissions as $submission) {
            if ($submission->assessment && $submission->assessment->items->isNotEmpty()) {
                $totalScore = AssessmentScoring::scoreSubmission($submission, $submission->assessment->items);
                $submission->update(['total_score' => $totalScore]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
