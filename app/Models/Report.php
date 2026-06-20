<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    public const TYPE_FORMATIVE = 'formative';

    public const TYPE_SUMMATIVE = 'summative';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    protected $primaryKey = 'report_id';

    protected $fillable = [
        'class_assessment_id',
        'report_type',
        'course_code_title',
        'ai_most_learned_draft',
        'ai_least_learned_draft',
        'concept_most_learned_skills',
        'concept_least_learned_skills',
        'issues_concern',
        'interventions_done',
        'future_plans_curriculum',
        'report_status',
    ];

    public function classAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassAssessment::class, 'class_assessment_id', 'class_assessment_id');
    }
}
