<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AcademicClass extends Model
{
    use HasFactory, UsesPublicId;

    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    protected $fillable = [
        'instructor_id',
        'subject_id',
        'year_level',
        'section_name',
        'class_name',
        'school_year',
        'join_token',
        'join_code',
        'archived_at',
    ];

    protected $casts = [
        'year_level' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function displayName(): string
    {
        if ($this->year_level && $this->section_name) {
            return 'Year '.$this->year_level.' - '.$this->section_name;
        }

        return $this->class_name ?? 'Class';
    }

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

    public function hasStudent(StudentProfile $studentProfile): bool
    {
        return $this->students()
            ->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)
            ->exists()
            || $this->classDetails()
                ->where('student_id', $studentProfile->student_profile_id)
                ->exists();
    }

    public function enrolledStudentIds(): array
    {
        $pivotIds = $this->students()
            ->pluck('student_profiles.student_profile_id');

        $detailIds = $this->classDetails()
            ->pluck('student_id');

        return $pivotIds
            ->merge($detailIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function enrolledStudentsCollection(array $with = []): Collection
    {
        $pivotStudents = $this->students()
            ->with($with)
            ->get();
        $detailStudentIds = $this->classDetails()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $detailStudents = $detailStudentIds->isNotEmpty()
            ? StudentProfile::query()
                ->with($with)
                ->whereIn('student_profile_id', $detailStudentIds)
                ->get()
            : collect();

        return $pivotStudents
            ->merge($detailStudents)
            ->unique('student_profile_id')
            ->values();
    }

    public function enrolledStudentsCount(): int
    {
        return count($this->enrolledStudentIds());
    }

    public function applyEnrolledStudentsCount(): self
    {
        $this->setAttribute('students_count', $this->enrolledStudentsCount());

        return $this;
    }

    public function enrollStudent(StudentProfile $studentProfile): void
    {
        DB::transaction(function () use ($studentProfile): void {
            if (! $this->students()
                ->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)
                ->exists()) {
                $this->students()->attach($studentProfile->student_profile_id);
            }

            $this->syncClassDetailForStudent($studentProfile);
        });
    }

    public function removeStudent(StudentProfile $studentProfile): void
    {
        DB::transaction(function () use ($studentProfile): void {
            $this->students()->detach($studentProfile->student_profile_id);

            $this->classDetails()
                ->where('student_id', $studentProfile->student_profile_id)
                ->delete();
        });
    }

    public function syncClassDetailForStudent(StudentProfile $studentProfile): ?ClassDetail
    {
        if (! $this->instructor_id || ! $this->subject_id) {
            return null;
        }

        $semester = Semester::activeOrDefault();

        return ClassDetail::query()->updateOrCreate(
            [
                'class_id' => $this->class_id,
                'student_id' => $studentProfile->student_profile_id,
                'subject_id' => $this->subject_id,
                'semester_id' => $semester->semester_id,
            ],
            [
                'instructor_id' => $this->instructor_id,
            ],
        );
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ClassJoinRequest::class, 'class_id', 'class_id');
    }

    public function classDetails(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'class_id', 'class_id');
    }

    public function classAssessments(): HasMany
    {
        return $this->hasMany(ClassAssessment::class, 'class_id', 'class_id');
    }
}
