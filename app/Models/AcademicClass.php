<?php

namespace App\Models;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
