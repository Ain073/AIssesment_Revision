<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionAnswer extends Model
{
    use HasFactory;

    protected $primaryKey = 'submission_answer_id';

    protected $fillable = [
        'submission_id',
        'assessment_item_id',
        'assessment_item_choice_id',
        'answer_text',
        'earned_points',
        'feedback',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_points' => 'decimal:2',
            'checked_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'submission_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class, 'assessment_item_id', 'assessment_item_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(AssessmentItemChoice::class, 'assessment_item_choice_id', 'assessment_item_choice_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by', 'id');
    }
}
