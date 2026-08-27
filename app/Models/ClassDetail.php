<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'class_details_id';

    protected $fillable = [
        'class_id',
        'instructor_id',
        'student_id',
        'subject_id',
        'semester_id',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id', 'class_id');
    }

    public function instructorProfile(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class, 'instructor_id', 'instructor_profile_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id', 'student_profile_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

}
