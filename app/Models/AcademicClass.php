<?php

namespace App\Models;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    protected $fillable = [
        'instructor_id',
        'subject_id',
        'class_name',
        'school_year',
        'join_token',
        'join_code',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function instructorProfile(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class, 'instructor_id', 'instructor_profile_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'class_students', 'class_id', 'student_profile_id')
            ->withTimestamps();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ClassJoinRequest::class, 'class_id', 'class_id');
    }

    public function classAssessments(): HasMany
    {
        return $this->hasMany(ClassAssessment::class, 'class_id', 'class_id');
    }
}
