<?php

namespace App\Http\Controllers\Instructor;

use App\Models\AcademicClass;
use App\Models\PublishAssessment;
use App\Models\ClassDetail;
use App\Models\StudentProfile;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassController extends BaseController
{
    public function classes(Request $request): View
    {
        $user = $this->currentUser();

        return view('instructor.classes.index', $this->sharedData($user, 'classes') + $this->instructorClassesData($user, $this->classListTab($request)));
    }

    public function classesLive(Request $request): View
    {
        return view('instructor.classes.class-list', $this->instructorClassesData($this->currentUser(), $this->classListTab($request)));
    }

    public function joinRequestsLive(Request $request, AcademicClass $class): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $this->ensureClassJoinAccess($ownedClass);

        $pendingJoinRequests = $ownedClass->joinRequests()
            ->where('status', ClassDetail::STATUS_PENDING)
            ->with(['studentProfile.user.roles', 'studentProfile.program.department.college'])
            ->latest('updated_at')
            ->get();

        $part = $request->query('part') === 'button' ? 'button' : 'list';

        return view("instructor.classes.join-requests-{$part}", [
            'class' => $ownedClass,
            'pendingJoinRequests' => $pendingJoinRequests,
        ]);
    }

    public function studentsLive(AcademicClass $class): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $this->ensureActiveClass($ownedClass);

        $enrolledStudents = $ownedClass->enrolledStudentsCollection(['user.roles', 'program.department.college'])
            ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
            ->values();

        return view('instructor.classes.student-table', [
            'class' => $ownedClass,
            'enrolledStudents' => $enrolledStudents,
            'studentPerformance' => $this->studentPerformanceByStudent($ownedClass),
        ]);
    }

    public function storeClass(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating classes.');

        $validated = $request->validate([
            'program_id' => ['required', 'integer', Rule::in($this->activeClassPrograms($instructorProfile)->pluck('program_id')->all())],
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'semester_id')],
            'subject_id' => ['required', 'integer', Rule::in($this->activeSubjectIds($instructorProfile)->all())],
            'year_level' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'section_name' => ['required', 'string', 'max:255'],
            'school_year_start' => ['required', 'digits:2'],
            'school_year_end' => ['required', 'digits:2'],
        ], [
            'school_year_start.digits' => 'Enter the first 2 digits of the academic year.',
            'school_year_end.digits' => 'Enter the last 2 digits of the academic year.',
        ]);

        $validated['section_name'] = $this->normalizeSectionName($validated['section_name']);
        $validated['school_year'] = $this->schoolYearFromParts($validated['school_year_start'], $validated['school_year_end']);
        $this->ensureClassIsUnique($instructorProfile->instructor_profile_id, $validated);

        $class = DB::transaction(function () use ($instructorProfile, $validated): AcademicClass {
            $class = AcademicClass::query()->create([
                'program_id' => $validated['program_id'],
                'semester_id' => $validated['semester_id'],
                'year_level' => $validated['year_level'],
                'section_name' => $validated['section_name'],
                'school_year' => $validated['school_year'],
                'join_token' => $this->generateClassJoinToken(),
                'join_code' => $this->generateClassJoinCode(),
            ]);

            $class->syncContext($instructorProfile, (int) $validated['subject_id']);

            return $class->refresh();
        });

        Log::info('Class created by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $class->class_id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'program_id' => $class->program_id,
            'semester_id' => $class->semester_id,
            'subject_id' => $class->subject_id,
            'year_level' => $class->year_level,
            'section_name' => $class->section_name,
            'school_year' => $class->school_year,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class added successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class added successfully.');
    }

    public function updateClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $allowedSubjectIds = $this->activeSubjectIds($instructorProfile)
            ->push($ownedClass->subject_id)
            ->filter()
            ->unique()
            ->all();
        $allowedProgramIds = $this->activeClassPrograms($instructorProfile)
            ->pluck('program_id')
            ->push($ownedClass->program_id)
            ->filter()
            ->unique()
            ->all();

        $validated = $request->validate([
            'program_id' => ['required', 'integer', Rule::in($allowedProgramIds)],
            'semester_id' => ['required', 'integer', Rule::exists('semesters', 'semester_id')],
            'subject_id' => ['required', 'integer', Rule::in($allowedSubjectIds)],
            'year_level' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'section_name' => ['required', 'string', 'max:255'],
            'school_year_start' => ['required', 'digits:2'],
            'school_year_end' => ['required', 'digits:2'],
        ], [
            'school_year_start.digits' => 'Enter the first 2 digits of the academic year.',
            'school_year_end.digits' => 'Enter the last 2 digits of the academic year.',
        ]);

        $validated['section_name'] = $this->normalizeSectionName($validated['section_name']);
        $validated['school_year'] = $this->schoolYearFromParts($validated['school_year_start'], $validated['school_year_end']);
        $this->ensureClassIsUnique($instructorProfile->instructor_profile_id, $validated, $ownedClass->class_id);

        DB::transaction(function () use ($ownedClass, $instructorProfile, $validated): void {
            $ownedClass->update([
                'program_id' => $validated['program_id'],
                'semester_id' => $validated['semester_id'],
                'year_level' => $validated['year_level'],
                'section_name' => $validated['section_name'],
                'school_year' => $validated['school_year'],
            ]);

            $ownedClass->syncContext($instructorProfile, (int) $validated['subject_id']);
            $ownedClass->unsetRelation('contextDetail');
            $ownedClass->refresh();
        });

        Log::info('Class updated by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
            'program_id' => $ownedClass->program_id,
            'semester_id' => $ownedClass->semester_id,
            'subject_id' => $ownedClass->subject_id,
            'year_level' => $ownedClass->year_level,
            'section_name' => $ownedClass->section_name,
            'school_year' => $ownedClass->school_year,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class updated successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class updated successfully.');
    }

    public function destroyClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);
        $classId = $ownedClass->class_id;
        $className = $ownedClass->class_name;

        DB::transaction(function () use ($ownedClass) {
            $ownedClass->classDetails()->delete();
            $ownedClass->publishAssessments()->delete();
            $ownedClass->delete();
        });

        Log::warning('Class deleted by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $classId,
            'class_name' => $className,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class deleted successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class deleted successfully.');
    }

    public function archiveClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $ownedClass->update(['archived_at' => now()]);

        Log::info('Class archived by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_name' => $ownedClass->class_name,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class archived successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class archived successfully.');
    }

    public function restoreClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $ownedClass->update(['archived_at' => null]);

        Log::info('Class restored by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_name' => $ownedClass->class_name,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class restored successfully.']);
        }

        return redirect()
            ->route('instructor.classes', ['tab' => 'archived'])
            ->with('status', 'Class restored successfully.');
    }

    public function showClass(Request $request, AcademicClass $class): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);
        $activeTab = in_array($request->query('tab'), ['overview', 'students', 'assessments'], true)
            ? $request->query('tab')
            : 'students';

        $ownedClass->load([
            'subject',
            'program.department.college',
            'semester',
            'instructorProfile.department.college',
            'students.user.roles',
            'students.program.department.college',
            'publishAssessments' => fn ($query) => $query
                ->with([
                    'assessment.subject',
                    'assessment.items.choices',
                    'submissions' => fn ($submissionQuery) => $submissionQuery
                        ->where('status', Submission::STATUS_SUBMITTED)
                        ->with('answers.choice'),
                ])
                ->latest('publish_assessment_id'),
            'joinRequests' => fn ($query) => $query
                ->where('status', ClassDetail::STATUS_PENDING)
                ->with(['studentProfile.user.roles', 'studentProfile.program.department.college'])
                ->latest('updated_at'),
        ])->loadCount('publishAssessments');

        $ownedClass->applyEnrolledStudentsCount();
        $enrolledStudents = $ownedClass->enrolledStudentsCollection(['user.roles', 'program.department.college'])
            ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
            ->values();

        $ownedClass->publishAssessments->each(function (PublishAssessment $publishAssessment): void {
            $publishAssessment->display_status = $publishAssessment->publish_status === PublishAssessment::STATUS_CLOSED
                || ($publishAssessment->due_at && $publishAssessment->due_at->isPast())
                    ? 'completed'
                    : 'pending';
        });

        $this->ensureClassJoinAccess($ownedClass);

        return view('instructor.classes.show', $this->sharedData($user, 'classes') + [
            'class' => $ownedClass,
            'activeTab' => $activeTab,
            'classTabs' => $this->classTabs($ownedClass, $activeTab),
            'classJoinLink' => route('student.classes.join.show', $ownedClass->join_token),
            'pendingJoinRequests' => $ownedClass->joinRequests
                ->sortByDesc('updated_at')
                ->values(),
            'enrolledStudents' => $enrolledStudents,
            'publishAssessments' => $ownedClass->publishAssessments,
            'studentPerformance' => $this->studentPerformanceByStudent($ownedClass),
            'importPreview' => $this->pullImportPreview($request, $ownedClass),
        ]);
    }

    private function normalizeSectionName(string $sectionName): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sectionName)));
    }

    private function schoolYearFromParts(string $startYearShort, string $endYearShort): string
    {
        $startYear = 2000 + (int) $startYearShort;
        $endYear = 2000 + (int) $endYearShort;

        if ($endYear !== $startYear + 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'school_year_end' => 'The academic year must be consecutive, such as 2026-2027.',
            ]);
        }

        return sprintf('%d-%d', $startYear, $endYear);
    }

    private function ensureClassIsUnique(int $instructorProfileId, array $classData, ?int $exceptClassId = null): void
    {
        $duplicateExists = AcademicClass::query()
            ->when($exceptClassId, fn ($query) => $query->where('class_id', '!=', $exceptClassId))
            ->where('year_level', $classData['year_level'])
            ->where('program_id', $classData['program_id'])
            ->where('semester_id', $classData['semester_id'])
            ->where('school_year', $classData['school_year'])
            ->whereRaw('LOWER(TRIM(section_name)) = ?', [strtolower($classData['section_name'])])
            ->whereHas('contextDetail', function ($query) use ($instructorProfileId, $classData): void {
                $query->where('instructor_id', $instructorProfileId)
                    ->where('subject_id', $classData['subject_id']);
            })
            ->exists();

        if (! $duplicateExists) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'section_name' => 'This class already exists for the selected subject, section, and academic year.',
        ]);
    }

}
