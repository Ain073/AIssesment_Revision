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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $studentProfile = $user->studentProfile;
        $classIds = $studentProfile
            ? $studentProfile->classes()
                ->whereNull('classes.archived_at')
                ->pluck('classes.class_id')
            : collect();
        $assignedAssessmentsCount = $classIds->isNotEmpty()
            ? ClassAssessment::query()
                ->whereIn('class_id', $classIds)
                ->where('publish_status', ClassAssessment::STATUS_PUBLISHED)
                ->count()
            : 0;
        $releasedResultsCount = $studentProfile
            ? Submission::query()
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_SUBMITTED)
                ->whereHas('classAssessment', fn ($query) => $query->where('score_visibility', true))
                ->distinct()
                ->count('class_assessment_id')
            : 0;

        return view('student.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Enrolled Classes',
                    'value' => $classIds->count(),
                    'caption' => 'Classes linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Assigned Assessments',
                    'value' => $assignedAssessmentsCount,
                    'caption' => 'Assessments currently available',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Released Results',
                    'value' => $releasedResultsCount,
                    'caption' => 'Scores and published outcomes',
                    'icon' => 'monitoring',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Classes',
                    'description' => 'View your enrolled classes and sections.',
                    'href' => route('student.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Take Assessments',
                    'description' => 'Open the assessments assigned to you.',
                    'href' => route('student.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'View Results',
                    'description' => 'Check released scores and assessment outcomes.',
                    'href' => route('student.results'),
                    'icon' => 'grading',
                ],
            ],
        ]);
    }

    public function classes(): View
    {
        $user = $this->currentUser();

        return view('student.classes', $this->sharedData($user, 'classes') + $this->studentClassesData($user));
    }

    public function classesLive(): View
    {
        return view('student.partials.classes-live', $this->studentClassesData($this->currentUser()));
    }

    public function classRequestsLive(): View
    {
        return view('student.partials.join-requests-table', $this->studentClassesData($this->currentUser()));
    }

    public function showClassJoinLink(string $token): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $class = AcademicClass::query()
            ->with(['subject', 'instructorProfile.user', 'instructorProfile.department.college'])
            ->where('join_token', $token)
            ->whereNull('archived_at')
            ->firstOrFail();

        $existingRequest = ClassJoinRequest::query()
            ->where('class_id', $class->class_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->first();
        $alreadyEnrolled = $class->students()
            ->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)
            ->exists();

        return view('student.class-join', $this->sharedData($user, 'classes') + [
            'class' => $class,
            'studentProfile' => $studentProfile,
            'existingRequest' => $existingRequest,
            'alreadyEnrolled' => $alreadyEnrolled,
        ]);
    }

    public function requestClassJoin(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $class = AcademicClass::query()
            ->where('join_token', $token)
            ->whereNull('archived_at')
            ->firstOrFail();

        return $this->submitClassJoinRequest($request, $class, $studentProfile, $user, 'link');
    }

    public function requestClassJoinByCode(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:20'],
        ]);

        $joinCode = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $validated['join_code']));

        $class = AcademicClass::query()
            ->where('join_code', $joinCode)
            ->whereNull('archived_at')
            ->first();

        if (! $class) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No class was found for that join code.',
                    'errors' => [
                        'join_code' => ['No class was found for that join code.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('student.classes')
                ->withErrors(['join_code' => 'No class was found for that join code.'])
                ->withInput();
        }

        return $this->submitClassJoinRequest($request, $class, $studentProfile, $user, 'code');
    }

    private function submitClassJoinRequest(Request $request, AcademicClass $class, StudentProfile $studentProfile, User $user, string $source): RedirectResponse|JsonResponse
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

    public function assessments(): View
    {
        $user = $this->currentUser();

        return view('student.assessments', $this->sharedData($user, 'assessments') + $this->studentAssessmentsData($user));
    }

    public function assessmentsLive(): View
    {
        return view('student.partials.assessments-live', $this->studentAssessmentsData($this->currentUser()));
    }

    public function takeAssessment(ClassAssessment $classAssessment): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($classAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        Log::info('Student opened published assessment.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'class_assessment_id' => $classAssessment->class_assessment_id,
            'assessment_id' => $classAssessment->assessment_id,
            'class_id' => $classAssessment->class_id,
        ]);

        return view('student.assessment-take', $this->sharedData($user, 'assessments') + [
            'classAssessment' => $classAssessment,
            'assessment' => $classAssessment->assessment,
            'class' => $classAssessment->class,
            'warningLimit' => $this->warningLimit($classAssessment),
        ]);
    }

    public function startAssessment(ClassAssessment $classAssessment): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($classAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        if ($classAssessment->assessment->items->isEmpty()) {
            return redirect()
                ->route('student.assessments.take', $classAssessment)
                ->withErrors(['assessment' => 'This assessment has no questions yet.']);
        }

        $submission = $this->startOrResumeSubmission($classAssessment, $studentProfile);

        if ($submission instanceof RedirectResponse) {
            return $submission;
        }

        $items = $classAssessment->assessment->items;

        if ($classAssessment->shuffle_items) {
            $items = $items->shuffle()->values();
        }

        if ($classAssessment->shuffle_choices) {
            $items->each(function ($item) {
                $item->setRelation('choices', $item->choices->shuffle()->values());
            });
        }

        Log::info('Student started assessment attempt view.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'class_assessment_id' => $classAssessment->class_assessment_id,
            'assessment_id' => $classAssessment->assessment_id,
            'class_id' => $classAssessment->class_id,
        ]);

        return view('student.assessment-attempt', [
            'user' => $user,
            'studentProfile' => $studentProfile,
            'classAssessment' => $classAssessment,
            'assessment' => $classAssessment->assessment,
            'class' => $classAssessment->class,
            'items' => $items,
            'submission' => $submission,
            'warningLimit' => $this->warningLimit($classAssessment),
        ]);
    }

    public function recordSecurityEvent(Request $request, ClassAssessment $classAssessment): JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        abort_if($studentProfile instanceof RedirectResponse, 403, 'A student profile is required.');

        $this->ensurePublishedAssessmentEnrollment($classAssessment, $studentProfile);

        $validated = $request->validate([
            'event_uuid' => ['required', 'string', 'max:64'],
            'event_type' => ['required', 'string', Rule::in(array_keys(SubmissionSecurityEvent::labels()))],
        ]);

        if (! $this->securityEventEnabled($classAssessment, $validated['event_type'])) {
            throw ValidationException::withMessages([
                'event_type' => 'This security event is not enabled for the assessment.',
            ]);
        }

        $result = DB::transaction(function () use ($request, $classAssessment, $studentProfile, $validated): array {
            $submission = Submission::query()
                ->where('class_assessment_id', $classAssessment->class_assessment_id)
                ->where('student_profile_id', $studentProfile->student_profile_id)
                ->where('status', Submission::STATUS_IN_PROGRESS)
                ->lockForUpdate()
                ->latest('attempt_number')
                ->first();

            abort_unless($submission, 409, 'There is no active assessment attempt.');

            $event = SubmissionSecurityEvent::query()->firstOrCreate(
                ['event_uuid' => $validated['event_uuid']],
                [
                    'submission_id' => $submission->submission_id,
                    'event_type' => $validated['event_type'],
                    'occurred_at' => now(),
                    'request_fingerprint' => hash_hmac(
                        'sha256',
                        (string) $request->ip().'|'.(string) $request->userAgent(),
                        (string) config('app.key'),
                    ),
                    'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                ],
            );

            abort_unless($event->submission_id === $submission->submission_id, 409, 'Security event identifier conflict.');

            $warningLimit = $this->warningLimit($classAssessment);

            if ($event->wasRecentlyCreated && $warningLimit > 0 && $submission->warning_count < $warningLimit) {
                $submission->warning_count++;
            }

            $submission->last_activity_at = now();
            $submission->save();

            return [
                'submission' => $submission,
                'recorded' => $event->wasRecentlyCreated,
                'warning_limit' => $warningLimit,
                'should_auto_submit' => $warningLimit > 0 && $submission->warning_count >= $warningLimit,
            ];
        });

        if ($result['recorded'] && $result['should_auto_submit']) {
            Log::warning('Assessment warning limit reached.', [
                'actor_id' => $user->id,
                'student_profile_id' => $studentProfile->student_profile_id,
                'class_assessment_id' => $classAssessment->class_assessment_id,
                'submission_id' => $result['submission']->submission_id,
            ]);
        }

        return response()->json([
            'recorded' => $result['recorded'],
            'warning_count' => $result['submission']->warning_count,
            'warning_limit' => $result['warning_limit'],
            'should_auto_submit' => $result['should_auto_submit'],
        ]);
    }

    public function submitAssessment(Request $request, ClassAssessment $classAssessment): RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($classAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        $classAssessment->load(['assessment.items.choices', 'class']);

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
        ]);

        $answers = collect($validated['answers'] ?? []);
        $items = $classAssessment->assessment->items;

        $submission = Submission::query()
            ->where('class_assessment_id', $classAssessment->class_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_IN_PROGRESS)
            ->latest('attempt_number')
            ->first();

        if (! $submission) {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'No active attempt was found. Start the assessment before submitting.']);
        }

        DB::transaction(function () use ($classAssessment, $items, $answers, $submission) {
            $lockedSubmission = Submission::query()
                ->whereKey($submission->submission_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->status !== Submission::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'assessment' => 'This assessment attempt was already submitted.',
                ]);
            }

            foreach ($items as $item) {
                $rawAnswer = $answers->get((string) $item->assessment_item_id);
                $choiceId = null;
                $answerText = null;

                if ($item->choices->isNotEmpty() && in_array($item->item_type, ['multiple_choice', 'true_false'], true)) {
                    $choiceId = $item->choices
                        ->pluck('assessment_item_choice_id')
                        ->contains((int) $rawAnswer)
                            ? (int) $rawAnswer
                            : null;
                } else {
                    $answerText = is_scalar($rawAnswer) ? trim((string) $rawAnswer) : null;
                }

                $lockedSubmission->answers()->create([
                    'assessment_item_id' => $item->assessment_item_id,
                    'assessment_item_choice_id' => $choiceId,
                    'answer_text' => $answerText,
                ]);
            }

            $warningLimit = $this->warningLimit($classAssessment);
            $lockedSubmission->update([
                'status' => Submission::STATUS_SUBMITTED,
                'completion_reason' => $warningLimit > 0 && $lockedSubmission->warning_count >= $warningLimit
                    ? Submission::COMPLETION_WARNING_LIMIT
                    : Submission::COMPLETION_MANUAL,
                'last_activity_at' => now(),
                'submitted_at' => now(),
            ]);
        });

        Log::info('Student submitted assessment.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'class_assessment_id' => $classAssessment->class_assessment_id,
            'assessment_id' => $classAssessment->assessment_id,
            'submission_id' => $submission->submission_id,
            'warning_count' => $submission->fresh()->warning_count,
        ]);

        return redirect()
            ->route('student.assessments')
            ->with('status', 'Assessment submitted successfully.');
    }

    public function results(): View
    {
        return view('student.results', $this->placeholderPageData(
            'results',
            'Results',
            'Released scores and assessment results will be organized here.',
            [
                'View released scores',
                'Review released assessment results',
                'Show answer review only when allowed by the instructor',
            ],
        ));
    }

    private function placeholderPageData(
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

    private function sharedData(User $user, string $activeNav): array
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

    private function navItems(string $activeNav): array
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

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'studentProfile.program.college');

        return $user;
    }

    private function studentProfileOrRedirect(User $user): StudentProfile|RedirectResponse
    {
        if ($user->studentProfile) {
            return $user->studentProfile;
        }

        return redirect()
            ->route('student.classes')
            ->withErrors(['student_profile' => 'Your student profile is not ready yet. Please contact the administrator.']);
    }

    private function studentClassesData(User $user): array
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

    private function studentAssessmentsData(User $user): array
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

    private function prepareAccessibleAssessment(ClassAssessment $classAssessment, StudentProfile $studentProfile): ?RedirectResponse
    {
        $this->ensurePublishedAssessmentEnrollment($classAssessment, $studentProfile);

        if ($this->studentAssessmentStatus($classAssessment) !== 'available') {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'This assessment is not available to take right now.']);
        }

        return null;
    }

    private function ensurePublishedAssessmentEnrollment(
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

    private function startOrResumeSubmission(
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

    private function securityEventEnabled(ClassAssessment $classAssessment, string $eventType): bool
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

    private function warningLimit(ClassAssessment $classAssessment): int
    {
        return max((int) ($classAssessment->warning_limit ?? 3), 0);
    }

    private function studentAssessmentStatus(ClassAssessment $classAssessment): string
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

    private function submittedSubmissionCount(ClassAssessment $classAssessment, StudentProfile $studentProfile): int
    {
        return Submission::query()
            ->where('class_assessment_id', $classAssessment->class_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_SUBMITTED)
            ->count();
    }
}
