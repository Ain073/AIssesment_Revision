<?php

namespace App\Http\Controllers\Instructor;

use App\Models\AcademicClass;
use App\Models\ClassAssessment;
use App\Models\ClassJoinRequest;
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

        return view('instructor.classes', $this->sharedData($user, 'classes') + $this->instructorClassesData($user, $this->classListTab($request)));
    }

    public function classesLive(Request $request): View
    {
        return view('instructor.partials.classes-live', $this->instructorClassesData($this->currentUser(), $this->classListTab($request)));
    }

    public function storeClass(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating classes.');

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($this->activeSubjectIds()->all())],
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $class = $instructorProfile->classes()->create($validated + [
            'join_token' => $this->generateClassJoinToken(),
            'join_code' => $this->generateClassJoinCode(),
        ]);

        Log::info('Class created by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $class->class_id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'subject_id' => $class->subject_id,
            'class_name' => $class->class_name,
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
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $ownedClass->update($validated);

        Log::info('Class updated by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
            'subject_id' => $ownedClass->subject_id,
            'class_name' => $ownedClass->class_name,
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
            $ownedClass->students()->detach();
            $ownedClass->joinRequests()->delete();
            $ownedClass->classAssessments()->delete();
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
        $this->ensureActiveClass($ownedClass);

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
            'students.program.college',
            'classAssessments' => fn ($query) => $query
                ->with([
                    'assessment.subject',
                    'assessment.items.choices',
                    'submissions' => fn ($submissionQuery) => $submissionQuery
                        ->where('status', Submission::STATUS_SUBMITTED)
                        ->with('answers.choice'),
                ])
                ->latest('class_assessment_id'),
            'joinRequests' => fn ($query) => $query
                ->where('status', ClassJoinRequest::STATUS_PENDING)
                ->with(['studentProfile.user.roles', 'studentProfile.program.college'])
                ->latest('requested_at'),
        ])->loadCount('students', 'classAssessments');

        $ownedClass->classAssessments->each(function (ClassAssessment $classAssessment): void {
            $classAssessment->display_status = $classAssessment->publish_status === ClassAssessment::STATUS_CLOSED
                || ($classAssessment->due_at && $classAssessment->due_at->isPast())
                    ? 'completed'
                    : 'pending';
        });

        $this->ensureClassJoinAccess($ownedClass);

        return view('instructor.class-show', $this->sharedData($user, 'classes') + [
            'class' => $ownedClass,
            'activeTab' => $activeTab,
            'classTabs' => $this->classTabs($ownedClass, $activeTab),
            'classJoinLink' => route('student.classes.join.show', $ownedClass->join_token),
            'pendingJoinRequests' => $ownedClass->joinRequests
                ->sortByDesc('requested_at')
                ->values(),
            'enrolledStudents' => $ownedClass->students
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values(),
            'classAssessments' => $ownedClass->classAssessments,
            'studentPerformance' => $this->studentPerformanceByStudent($ownedClass),
            'importPreview' => $this->pullImportPreview($request, $ownedClass),
        ]);
    }
}
