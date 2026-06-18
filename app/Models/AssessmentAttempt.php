<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAttempt extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    protected $primaryKey = 'assessment_attempt_id';

    protected $fillable = [
        'class_assessment_id',
        'student_profile_id',
        'attempt_number',
        'status',
        'warnings_used',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function classAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassAssessment::class, 'class_assessment_id', 'class_assessment_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id', 'student_profile_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'assessment_attempt_id', 'assessment_attempt_id');
    }
}
