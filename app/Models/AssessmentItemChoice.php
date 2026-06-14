<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentItemChoice extends Model
{
    use HasFactory;

    protected $primaryKey = 'assessment_item_choice_id';

    protected $fillable = [
        'assessment_item_id',
        'choice_text',
        'is_correct',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class, 'assessment_item_id', 'assessment_item_id');
    }
}
