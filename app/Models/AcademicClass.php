<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            StudentProfile::class,
            ClassDetail::class,
            'class_id',
            'student_profile_id',
            'class_id',
            'student_id'
        )->where('class_details.status', ClassDetail::STATUS_APPROVED);
    }

    public function hasStudent(StudentProfile $studentProfile): bool
    {
        return $this->classDetails()
            ->where('student_id', $studentProfile->student_profile_id)
            ->where('status', ClassDetail::STATUS_APPROVED)
            ->exists();
    }

    public function hasClassDetailForStudent(StudentProfile $studentProfile): bool
    {
        return $this->classDetails()
            ->where('student_id', $studentProfile->student_profile_id)
            ->exists();
    }

    public function enrolledStudentIds(): array
    {
        return $this->classDetails()
            ->where('status', ClassDetail::STATUS_APPROVED)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function enrolledStudentsCollection(array $with = []): Collection
    {
        $detailStudentIds = $this->classDetails()
            ->where('status', ClassDetail::STATUS_APPROVED)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $detailStudentIds->isNotEmpty()
            ? StudentProfile::query()
                ->with($with)
                ->whereIn('student_profile_id', $detailStudentIds)
                ->get()
            : collect();
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
        $this->addStudentWithMethod($studentProfile, ClassDetail::METHOD_MANUAL_ADD);
    }

    public function addStudentWithMethod(StudentProfile $studentProfile, string $entryMethod): void
    {
        DB::transaction(function () use ($studentProfile, $entryMethod): void {
            $this->syncClassDetailForStudent($studentProfile, ClassDetail::STATUS_APPROVED, $entryMethod);
        });
    }

    public function requestStudentJoin(StudentProfile $studentProfile): ?ClassDetail
    {
        return $this->syncClassDetailForStudent($studentProfile, ClassDetail::STATUS_PENDING, ClassDetail::METHOD_JOIN_CODE);
    }

    public function removeStudent(StudentProfile $studentProfile): void
    {
        DB::transaction(function () use ($studentProfile): void {
            $this->classDetails()
                ->where('student_id', $studentProfile->student_profile_id)
                ->delete();
        });
    }

    public function syncClassDetailForStudent(
        StudentProfile $studentProfile,
        string $status = ClassDetail::STATUS_APPROVED,
        string $entryMethod = ClassDetail::METHOD_MANUAL_ADD
    ): ?ClassDetail
    {
        if (! $this->instructor_id || ! $this->subject_id) {
            return null;
        }

        return ClassDetail::query()->updateOrCreate(
            [
                'class_id' => $this->class_id,
                'student_id' => $studentProfile->student_profile_id,
                'subject_id' => $this->subject_id,
            ],
            [
                'instructor_id' => $this->instructor_id,
                'status' => $status,
                'entry_method' => $entryMethod,
            ],
        );
    }

    public function publishContextClassDetail(): ?ClassDetail
    {
        return $this->classDetails()
            ->where('instructor_id', $this->instructor_id)
            ->where('subject_id', $this->subject_id)
            ->where('status', ClassDetail::STATUS_APPROVED)
            ->latest('class_details_id')
            ->first();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'class_id', 'class_id')
            ->where('entry_method', ClassDetail::METHOD_JOIN_CODE);
    }

    public function classDetails(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'class_id', 'class_id');
    }

    public function classAssessments(): HasManyThrough
    {
        return $this->hasManyThrough(
            ClassAssessment::class,
            ClassDetail::class,
            'class_id',
            'class_details_id',
            'class_id',
            'class_details_id'
        );
    }
}
