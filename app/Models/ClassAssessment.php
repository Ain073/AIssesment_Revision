<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClassAssessment extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const DISPLAY_ALL_QUESTIONS = 'all_questions';

    public const DISPLAY_ONE_QUESTION = 'one_question';

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

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id', 'class_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'class_assessment_id', 'class_assessment_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'class_assessment_id', 'class_assessment_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'class_assessment_id', 'class_assessment_id');
    }
}
