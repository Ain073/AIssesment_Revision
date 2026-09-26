<?php

use App\Models\AssessmentItem;
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
    }

    public function down(): void
    {
        //
    }
};
