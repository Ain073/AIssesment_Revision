<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Helpers\StudentLayoutHelper;
use App\Models\AcademicClass;
use App\Models\PublishAssessment;
use App\Models\ClassDetail;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\SubmissionSecurityEvent;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{
    use StudentLayoutHelper;

    protected function submitClassJoinRequest(Request $request, AcademicClass $class, StudentProfile $studentProfile, User $user, string $source): RedirectResponse|JsonResponse
    {
        $existingClassDetail = $class->classDetails()
            ->where('student_id', $studentProfile->student_profile_id)
            ->first();

        if ($existingClassDetail?->status === ClassDetail::STATUS_APPROVED || $class->hasStudent($studentProfile)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You are already enrolled in that class.']);
            }

            return redirect()
                ->route('student.classes')
                ->with('status', 'You are already enrolled in that class.');
        }

        if ($existingClassDetail?->status === ClassDetail::STATUS_PENDING) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Join request already pending. Please wait for your teacher to approve it.']);
            }

            return redirect()
                ->route('student.classes')
                ->with('status', 'Join request already pending. Please wait for your teacher to approve it.');
        }

        $joinRequest = $class->requestStudentJoin($studentProfile);

        Log::info('Student requested to join class.', [
            'actor_id' => $user->id,
            'source' => $source,
            'class_id' => $class->class_id,
            'class_details_id' => $joinRequest?->class_details_id,
            'student_profile_id' => $studentProfile->student_profile_id,
        ]);

        $class->loadMissing('instructorProfile.user');

        if ($class->instructorProfile?->user) {
            app(NotificationService::class)->send(
                $class->instructorProfile->user,
                'Class Join Request',
                $user->displayName().' requested to join '.$class->class_name.'.',
                route('instructor.classes.show', ['class' => $class, 'tab' => 'students']),
                'class'
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Join request sent. Please wait for your teacher to approve it.']);
        }

        return redirect()
            ->route('student.classes')
            ->with('status', 'Join request sent. Please wait for your teacher to approve it.');
    }

    protected function studentProfileOrRedirect(User $user): StudentProfile|RedirectResponse
    {
        if ($user->studentProfile) {
            return $user->studentProfile;
        }

        return redirect()
            ->route('student.classes')
            ->withErrors(['student_profile' => 'Your student profile is not ready yet. Please contact the administrator.']);
    }

    protected function studentClassesData(User $user): array
    {
        $studentProfile = $user->studentProfile;
        $enrolledClasses = $studentProfile
            ? $this->classesForStudent($studentProfile)
                ->with(['subject', 'instructorProfile.user'])
                ->whereNull('classes.archived_at')
                ->latest('classes.class_id')
                ->get()
            : collect();
        $archivedClasses = $studentProfile
            ? $this->classesForStudent($studentProfile)
                ->with(['subject', 'instructorProfile.user'])
                ->whereNotNull('classes.archived_at')
                ->latest('classes.archived_at')
                ->get()
            : collect();
        $joinRequests = $studentProfile
            ? $studentProfile->classDetails()
                ->where('entry_method', ClassDetail::METHOD_JOIN_CODE)
                ->with(['class.subject', 'class.instructorProfile.user'])
                ->latest('updated_at')
                ->get()
            : collect();

        return [
            'studentProfile' => $studentProfile,
            'enrolledClasses' => $enrolledClasses,
            'archivedClasses' => $archivedClasses,
            'joinRequests' => $joinRequests,
        ];
    }

    protected function studentAssessmentsData(User $user): array
    {
        $studentProfile = $user->studentProfile;
        $classIds = $studentProfile
            ? $this->classesForStudent($studentProfile)
                ->whereNull('classes.archived_at')
                ->pluck('classes.class_id')
            : collect();
        $publishAssessments = $classIds->isNotEmpty()
            ? PublishAssessment::query()
                ->with(['assessment.subject', 'assessment.items.choices', 'classDetail.class.instructorProfile.user'])
                ->whereHas('classDetail', fn ($query) => $query->whereIn('class_id', $classIds))
                ->where('publish_status', PublishAssessment::STATUS_PUBLISHED)
                ->latest('publish_assessment_id')
                ->get()
                ->map(function (PublishAssessment $publishAssessment): PublishAssessment {
                    $publishAssessment->student_status = $this->studentAssessmentStatus($publishAssessment);

                    return $publishAssessment;
                })
                ->reject(fn (PublishAssessment $publishAssessment): bool => $publishAssessment->student_status === 'completed')
                ->values()
            : collect();

        return [
            'studentProfile' => $studentProfile,
            'publishAssessments' => $publishAssessments,
        ];
    }

    protected function prepareAccessibleAssessment(PublishAssessment $publishAssessment, StudentProfile $studentProfile): ?RedirectResponse
    {
        $this->ensurePublishedAssessmentEnrollment($publishAssessment, $studentProfile);

        if ($this->studentAssessmentStatus($publishAssessment) !== 'available') {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'This assessment is not available to take right now.']);
        }

        return null;
    }

    protected function ensurePublishedAssessmentEnrollment(
        PublishAssessment $publishAssessment,
        StudentProfile $studentProfile
    ): void {
        $publishAssessment->load(['assessment.subject', 'assessment.items.choices', 'classDetail.class.instructorProfile.user']);

        abort_unless(
            $publishAssessment->publish_status === PublishAssessment::STATUS_PUBLISHED
                && $publishAssessment->class
                && $publishAssessment->hasStudent($studentProfile),
            403,
            'You are not allowed to open this assessment.'
        );
    }

    protected function classesForStudent(StudentProfile $studentProfile)
    {
        return AcademicClass::query()
            ->where(function ($query) use ($studentProfile): void {
                $query->whereHas('classDetails', fn ($detailQuery) => $detailQuery
                    ->where('student_id', $studentProfile->student_profile_id)
                    ->where('status', ClassDetail::STATUS_APPROVED));
            });
    }

    protected function startOrResumeSubmission(
        PublishAssessment $publishAssessment,
        StudentProfile $studentProfile
    ): Submission|RedirectResponse {
        return DB::transaction(function () use ($publishAssessment, $studentProfile): Submission|RedirectResponse {
            PublishAssessment::query()
                ->whereKey($publishAssessment->publish_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeSubmission = Submission::query()
                ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_IN_PROGRESS)
                ->latest('attempt_number')
                ->first();

            if ($activeSubmission) {
                $activeSubmission->update(['last_activity_at' => now()]);

                return $activeSubmission;
            }

            $attemptLimit = max((int) $publishAssessment->attempt_limit, 1);
            $attemptsUsed = $this->submittedSubmissionCount($publishAssessment, $studentProfile);

            if ($attemptsUsed >= $attemptLimit) {
                return redirect()
                    ->route('student.assessments')
                    ->withErrors(['assessment' => 'You already used all allowed attempts for this assessment.']);
            }

            $attemptNumber = ((int) Submission::query()
                ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->max('attempt_number')) + 1;

            return Submission::query()->create([
                'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                'student_profile_id' => $studentProfile->student_profile_id,
                'attempt_number' => $attemptNumber,
                'status' => Submission::STATUS_IN_PROGRESS,
                'warning_count' => 0,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        });
    }

    protected function securityEventEnabled(PublishAssessment $publishAssessment, string $eventType): bool
    {
        return match ($eventType) {
            SubmissionSecurityEvent::TYPE_COPY,
            SubmissionSecurityEvent::TYPE_CUT,
            SubmissionSecurityEvent::TYPE_PASTE,
            SubmissionSecurityEvent::TYPE_CONTEXT_MENU => (bool) $publishAssessment->prevent_copy_paste,
            SubmissionSecurityEvent::TYPE_TAB_HIDDEN,
            SubmissionSecurityEvent::TYPE_WINDOW_BLUR,
            SubmissionSecurityEvent::TYPE_FLOATING_WINDOW => (bool) $publishAssessment->detect_tab_switch,
            SubmissionSecurityEvent::TYPE_PRINT_SHORTCUT,
            SubmissionSecurityEvent::TYPE_SCREENSHOT_SHORTCUT => (bool) $publishAssessment->screenshot_protection,
            default => false,
        };
    }

    protected function warningLimit(PublishAssessment $publishAssessment): int
    {
        return max((int) ($publishAssessment->warning_limit ?? 3), 0);
    }

    protected function studentAssessmentStatus(PublishAssessment $publishAssessment): string
    {
        $now = now();

        if ($publishAssessment->due_at && $publishAssessment->due_at->copy()->endOfMinute()->lt($now)) {
            return 'completed';
        }

        $studentProfile = $this->currentUser()->studentProfile;

        if ($studentProfile && $this->submittedSubmissionCount($publishAssessment, $studentProfile) >= max((int) $publishAssessment->attempt_limit, 1)) {
            return 'completed';
        }

        if ($publishAssessment->available_at && $publishAssessment->available_at->copy()->startOfMinute()->gt($now)) {
            return 'pending';
        }

        return 'available';
    }

    protected function submittedSubmissionCount(PublishAssessment $publishAssessment, StudentProfile $studentProfile): int
    {
        return Submission::query()
            ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_SUBMITTED)
            ->count();
    }
}
