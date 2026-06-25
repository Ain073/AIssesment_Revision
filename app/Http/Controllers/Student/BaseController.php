<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\ClassAssessment;
use App\Models\ClassJoinRequest;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\SubmissionSecurityEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BaseController extends Controller
{
    protected function submitClassJoinRequest(Request $request, AcademicClass $class, StudentProfile $studentProfile, User $user, string $source): RedirectResponse|JsonResponse
    {
        if ($class->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You are already enrolled in that class.']);
            }

            return redirect()
                ->route('student.classes')
                ->with('status', 'You are already enrolled in that class.');
        }

        $joinRequest = ClassJoinRequest::query()->updateOrCreate(
            [
                'class_id' => $class->class_id,
                'student_profile_id' => $studentProfile->student_profile_id,
            ],
            [
                'status' => ClassJoinRequest::STATUS_PENDING,
                'requested_at' => now(),
                'responded_at' => null,
                'responded_by' => null,
            ],
        );

        Log::info('Student requested to join class.', [
            'actor_id' => $user->id,
            'source' => $source,
            'class_id' => $class->class_id,
            'class_join_request_id' => $joinRequest->class_join_request_id,
            'student_profile_id' => $studentProfile->student_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Join request sent. Please wait for your teacher to approve it.']);
        }

        return redirect()
            ->route('student.classes')
            ->with('status', 'Join request sent. Please wait for your teacher to approve it.');
    }

    protected function placeholderPageData(
        string $activeNav,
        string $pageHeading,
        string $pageDescription,
        array $checklist
    ): array {
        $user = $this->currentUser();

        return $this->sharedData($user, $activeNav) + [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'pageChecklist' => $checklist,
        ];
    }

    protected function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Student Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Student Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => [],
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search classes or assessments...',
        ];
    }

    protected function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments')],
            ['key' => 'results', 'label' => 'Results', 'icon' => 'grading', 'href' => route('student.results')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'studentProfile.program.college');

        return $user;
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
            ? $studentProfile->classes()
                ->with(['subject', 'instructorProfile.user'])
                ->whereNull('classes.archived_at')
                ->latest('classes.class_id')
                ->get()
            : collect();
        $archivedClasses = $studentProfile
            ? $studentProfile->classes()
                ->with(['subject', 'instructorProfile.user'])
                ->whereNotNull('classes.archived_at')
                ->latest('classes.archived_at')
                ->get()
            : collect();
        $joinRequests = $studentProfile
            ? $studentProfile->classJoinRequests()
                ->with(['class.subject', 'class.instructorProfile.user'])
                ->latest('requested_at')
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
            ? $studentProfile->classes()
                ->whereNull('classes.archived_at')
                ->pluck('classes.class_id')
            : collect();
        $classAssessments = $classIds->isNotEmpty()
            ? ClassAssessment::query()
                ->with(['assessment.subject', 'assessment.items.choices', 'class.instructorProfile.user'])
                ->whereIn('class_id', $classIds)
                ->where('publish_status', ClassAssessment::STATUS_PUBLISHED)
                ->latest('class_assessment_id')
                ->get()
                ->each(fn (ClassAssessment $classAssessment) => $classAssessment->student_status = $this->studentAssessmentStatus($classAssessment))
            : collect();

        return [
            'studentProfile' => $studentProfile,
            'classAssessments' => $classAssessments,
            'availableCount' => $classAssessments->where('student_status', 'available')->count(),
            'pendingCount' => $classAssessments->where('student_status', 'pending')->count(),
            'completedCount' => $classAssessments->where('student_status', 'completed')->count(),
        ];
    }

    protected function prepareAccessibleAssessment(ClassAssessment $classAssessment, StudentProfile $studentProfile): ?RedirectResponse
    {
        $this->ensurePublishedAssessmentEnrollment($classAssessment, $studentProfile);

        if ($this->studentAssessmentStatus($classAssessment) !== 'available') {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'This assessment is not available to take right now.']);
        }

        return null;
    }

    protected function ensurePublishedAssessmentEnrollment(
        ClassAssessment $classAssessment,
        StudentProfile $studentProfile
    ): void {
        $classAssessment->load(['assessment.subject', 'assessment.items.choices', 'class.instructorProfile.user']);

        abort_unless(
            $classAssessment->publish_status === ClassAssessment::STATUS_PUBLISHED
                && $classAssessment->class
                && $classAssessment->class->students()
                    ->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)
                    ->exists(),
            403,
            'You are not allowed to open this assessment.'
        );
    }

    protected function startOrResumeSubmission(
        ClassAssessment $classAssessment,
        StudentProfile $studentProfile
    ): Submission|RedirectResponse {
        return DB::transaction(function () use ($classAssessment, $studentProfile): Submission|RedirectResponse {
            ClassAssessment::query()
                ->whereKey($classAssessment->class_assessment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeSubmission = Submission::query()
                ->where('class_assessment_id', $classAssessment->class_assessment_id)
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_IN_PROGRESS)
                ->latest('attempt_number')
                ->first();

            if ($activeSubmission) {
                $activeSubmission->update(['last_activity_at' => now()]);

                return $activeSubmission;
            }

            $attemptLimit = max((int) $classAssessment->attempt_limit, 1);
            $attemptsUsed = $this->submittedSubmissionCount($classAssessment, $studentProfile);

            if ($attemptsUsed >= $attemptLimit) {
                return redirect()
                    ->route('student.assessments')
                    ->withErrors(['assessment' => 'You already used all allowed attempts for this assessment.']);
            }

            $attemptNumber = ((int) Submission::query()
                ->where('class_assessment_id', $classAssessment->class_assessment_id)
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->max('attempt_number')) + 1;

            return Submission::query()->create([
                'class_assessment_id' => $classAssessment->class_assessment_id,
                'student_profile_id' => $studentProfile->student_profile_id,
                'attempt_number' => $attemptNumber,
                'status' => Submission::STATUS_IN_PROGRESS,
                'warning_count' => 0,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        });
    }

    protected function securityEventEnabled(ClassAssessment $classAssessment, string $eventType): bool
    {
        return match ($eventType) {
            SubmissionSecurityEvent::TYPE_COPY,
            SubmissionSecurityEvent::TYPE_CUT,
            SubmissionSecurityEvent::TYPE_PASTE,
            SubmissionSecurityEvent::TYPE_CONTEXT_MENU => (bool) $classAssessment->prevent_copy_paste,
            SubmissionSecurityEvent::TYPE_TAB_HIDDEN,
            SubmissionSecurityEvent::TYPE_WINDOW_BLUR => (bool) $classAssessment->detect_tab_switch,
            SubmissionSecurityEvent::TYPE_PRINT_SHORTCUT,
            SubmissionSecurityEvent::TYPE_SCREENSHOT_SHORTCUT => (bool) $classAssessment->screenshot_protection,
            default => false,
        };
    }

    protected function warningLimit(ClassAssessment $classAssessment): int
    {
        return max((int) ($classAssessment->warning_limit ?? 3), 0);
    }

    protected function studentAssessmentStatus(ClassAssessment $classAssessment): string
    {
        $now = now();

        if ($classAssessment->due_at && $classAssessment->due_at->copy()->endOfMinute()->lt($now)) {
            return 'completed';
        }

        $studentProfile = $this->currentUser()->studentProfile;

        if ($studentProfile && $this->submittedSubmissionCount($classAssessment, $studentProfile) >= max((int) $classAssessment->attempt_limit, 1)) {
            return 'completed';
        }

        if ($classAssessment->available_at && $classAssessment->available_at->copy()->startOfMinute()->gt($now)) {
            return 'pending';
        }

        return 'available';
    }

    protected function submittedSubmissionCount(ClassAssessment $classAssessment, StudentProfile $studentProfile): int
    {
        return Submission::query()
            ->where('class_assessment_id', $classAssessment->class_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_SUBMITTED)
            ->count();
    }
}
