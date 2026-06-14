<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'assessment_item_id';

    protected $fillable = [
        'assessment_id',
        'question_text',
        'item_type',
        'points',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'points' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(AssessmentItemChoice::class, 'assessment_item_id', 'assessment_item_id')
            ->orderBy('sort_order');
    }
}
