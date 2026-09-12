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
            'subject_id' => ['required', 'integer', Rule::in($this->activeSubjectIds()->all())],
            'year_level' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'section_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        $class = DB::transaction(function () use ($instructorProfile, $validated): AcademicClass {
            $class = AcademicClass::query()->create([
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

        $allowedSubjectIds = $this->activeSubjectIds()
            ->push($ownedClass->subject_id)
            ->filter()
            ->unique()
            ->all();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($allowedSubjectIds)],
            'year_level' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'section_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        DB::transaction(function () use ($ownedClass, $instructorProfile, $validated): void {
            $ownedClass->update([
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

}
