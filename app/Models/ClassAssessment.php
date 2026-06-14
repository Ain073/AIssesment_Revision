<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAssessment extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'class_assessment';

    protected $primaryKey = 'class_assessment_id';

    protected $fillable = [
        'assessment_id',
        'class_id',
        'available_at',
        'due_at',
        'publish_status',
        'score_visibility',
        'answer_visibility',
        'attempt_limit',
        'shuffle_items',
        'shuffle_choices',
        'warning_limit',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'due_at' => 'datetime',
            'score_visibility' => 'boolean',
            'answer_visibility' => 'boolean',
            'shuffle_items' => 'boolean',
            'shuffle_choices' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id', 'class_id');
    }
}
