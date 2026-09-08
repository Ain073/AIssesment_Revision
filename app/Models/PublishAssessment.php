<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublishAssessment extends Model
{
    use HasFactory, UsesPublicId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const DISPLAY_ALL_QUESTIONS = 'all_questions';

    public const DISPLAY_ONE_QUESTION = 'one_question';

    protected $table = 'publish_assessment';

    protected $primaryKey = 'publish_assessment_id';

    protected $fillable = [
        'assessment_id',
        'class_details_id',
        'available_at',
        'due_at',
        'publish_status',
        'score_visibility',
        'answer_visibility',
        'prevent_copy_paste',
        'detect_tab_switch',
        'screenshot_protection',
        'attempt_limit',
        'shuffle_items',
        'shuffle_choices',
        'warning_limit',
        'display_mode',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'due_at' => 'datetime',
            'score_visibility' => 'boolean',
            'answer_visibility' => 'boolean',
            'prevent_copy_paste' => 'boolean',
            'detect_tab_switch' => 'boolean',
            'screenshot_protection' => 'boolean',
            'shuffle_items' => 'boolean',
            'shuffle_choices' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }

    public function classDetail(): BelongsTo
    {
        return $this->belongsTo(ClassDetail::class, 'class_details_id', 'class_details_id');
    }

    public function hasStudent(StudentProfile $studentProfile): bool
    {
        return $this->class && $this->class->hasStudent($studentProfile);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'publish_assessment_id', 'publish_assessment_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'publish_assessment_id', 'publish_assessment_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'publish_assessment_id', 'publish_assessment_id');
    }

    public function getPublishAssessmentIdAttribute(): ?int
    {
        $id = $this->attributes['publish_assessment_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function getClassIdAttribute(): ?int
    {
        return $this->classDetail?->class_id;
    }

    public function getClassAttribute(): ?AcademicClass
    {
        $classDetail = $this->relationLoaded('classDetail')
            ? $this->getRelation('classDetail')
            : $this->classDetail()->with('class')->first();

        return $classDetail?->class;
    }
}
