<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    use HasFactory;

    protected $primaryKey = 'assessment_answer_id';

    protected $fillable = [
        'assessment_attempt_id',
        'assessment_item_id',
        'assessment_item_choice_id',
        'answer_text',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id', 'assessment_attempt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class, 'assessment_item_id', 'assessment_item_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(AssessmentItemChoice::class, 'assessment_item_choice_id', 'assessment_item_choice_id');
    }
}
