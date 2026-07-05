<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory, UsesPublicId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_ARCHIVED = 'archived';

    protected $primaryKey = 'assessment_id';

    protected $fillable = [
        'instructor_id',
        'subject_id',
        'title',
        'description',
        'type',
        'report_category',
        'reporting_term',
        'instructions',
        'status',
    ];

    public function instructorProfile(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class, 'instructor_id', 'instructor_profile_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'assessment_id', 'assessment_id')
            ->orderBy('sort_order');
    }

    public function classAssessments(): HasMany
    {
        return $this->hasMany(ClassAssessment::class, 'assessment_id', 'assessment_id');
    }
}
