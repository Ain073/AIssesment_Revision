<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Support\YearLevel;

class AcademicClass extends Model
{
    use HasFactory, UsesPublicId;

    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    protected $fillable = [
        'year_level',
        'section_name',
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
            return YearLevel::label($this->year_level).' - '.$this->section_name;
        }

        return 'Class';
    }

    public function yearLevelLabel(): string
    {
        return YearLevel::label($this->year_level);
    }

    public function contextDetail(): HasOne
    {
        return $this->hasOne(ClassDetail::class, 'class_id', 'class_id')
            ->whereNull('student_id');
    }

    public function instructorProfile(): HasOneThrough
    {
        return $this->hasOneThrough(
            InstructorProfile::class,
            ClassDetail::class,
            'class_id',
            'instructor_profile_id',
            'class_id',
            'instructor_id'
        )->whereNull('class_details.student_id');
    }

    public function subject(): HasOneThrough
    {
        return $this->hasOneThrough(
            Subject::class,
            ClassDetail::class,
            'class_id',
            'subject_id',
            'class_id',
            'subject_id'
        )->whereNull('class_details.student_id');
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
            ->whereNotNull('student_id')
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
            ->whereNotNull('student_id')
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
        $context = $this->classContext();

        if (! $context) {
            return null;
        }

        return ClassDetail::query()->updateOrCreate(
            [
                'class_id' => $this->class_id,
                'student_id' => $studentProfile->student_profile_id,
                'subject_id' => $context->subject_id,
            ],
            [
                'instructor_id' => $context->instructor_id,
                'status' => $status,
                'entry_method' => $entryMethod,
            ],
        );
    }

    public function syncContext(InstructorProfile $instructorProfile, int $subjectId): ClassDetail
    {
        return ClassDetail::query()->updateOrCreate(
            [
                'class_id' => $this->class_id,
                'student_id' => null,
            ],
            [
                'instructor_id' => $instructorProfile->instructor_profile_id,
                'subject_id' => $subjectId,
                'status' => ClassDetail::STATUS_APPROVED,
                'entry_method' => ClassDetail::METHOD_CLASS_SETUP,
            ],
        );
    }

    public function publishContextClassDetail(): ?ClassDetail
    {
        return $this->classContext()
            ?: $this->classDetails()
            ->whereNotNull('student_id')
            ->where('status', ClassDetail::STATUS_APPROVED)
            ->latest('class_details_id')
            ->first();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'class_id', 'class_id')
            ->whereNotNull('student_id')
            ->where('entry_method', ClassDetail::METHOD_JOIN_CODE);
    }

    public function classDetails(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'class_id', 'class_id');
    }

    public function publishAssessments(): HasManyThrough
    {
        return $this->hasManyThrough(
            PublishAssessment::class,
            ClassDetail::class,
            'class_id',
            'class_details_id',
            'class_id',
            'class_details_id'
        );
    }

    public function getClassNameAttribute($value): string
    {
        return $value ?: $this->displayName();
    }

    public function getSchoolYearAttribute($value): ?string
    {
        return $value;
    }

    public function getInstructorIdAttribute($value): ?int
    {
        $id = $value ?? $this->classContext()?->instructor_id;

        return $id === null ? null : (int) $id;
    }

    public function getSubjectIdAttribute($value): ?int
    {
        $id = $value ?? $this->classContext()?->subject_id;

        return $id === null ? null : (int) $id;
    }

    private function classContext(): ?ClassDetail
    {
        if ($this->relationLoaded('contextDetail')) {
            return $this->getRelation('contextDetail');
        }

        return $this->contextDetail()->first();
    }
}
