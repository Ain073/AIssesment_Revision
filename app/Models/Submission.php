<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const COMPLETION_MANUAL = 'manual_submit';

    public const COMPLETION_WARNING_LIMIT = 'warning_limit';

    protected $primaryKey = 'submission_id';

    protected $fillable = [
        'class_assessment_id',
        'student_profile_id',
        'attempt_number',
        'status',
        'completion_reason',
        'warning_count',
        'started_at',
        'last_activity_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
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
        return $this->hasMany(SubmissionAnswer::class, 'submission_id', 'submission_id');
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SubmissionSecurityEvent::class, 'submission_id', 'submission_id')
            ->orderBy('occurred_at');
    }
}
