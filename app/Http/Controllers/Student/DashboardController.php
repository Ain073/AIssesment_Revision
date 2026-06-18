<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\ClassAssessment;
use App\Models\ClassJoinRequest;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();

        return view('student.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Enrolled Classes',
                    'value' => 0,
                    'caption' => 'Classes linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Assigned Assessments',
                    'value' => 0,
                    'caption' => 'Assessments currently available',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Released Results',
                    'value' => 0,
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
            'studentNotes' => [
                [
                    'title' => 'Classes and assessments',
                    'description' => 'Your enrolled classes and assigned assessments will appear here once class roster and publishing flow are connected.',
                ],
                [
                    'title' => 'Released results only',
                    'description' => 'Results should only appear when the instructor allows score release based on the manuscript workflow.',
                ],
                [
                    'title' => 'Answer review depends on instructor settings',
                    'description' => 'If allowed after closing, this portal can also show your submitted answers and correct answers for review.',
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
            'warningLimit' => $this->warningLimit($classAssessment),
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

        $submissionsUsed = $this->submittedSubmissionCount($classAssessment, $studentProfile);
        $attemptLimit = max((int) $classAssessment->attempt_limit, 1);

        if ($submissionsUsed >= $attemptLimit) {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'You already used the allowed attempt for this assessment.']);
        }

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'warning_count' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $answers = collect($validated['answers'] ?? []);
        $items = $classAssessment->assessment->items;

        DB::transaction(function () use ($classAssessment, $studentProfile, $items, $answers, $validated, $submissionsUsed) {
            $submission = Submission::query()->create([
                'class_assessment_id' => $classAssessment->class_assessment_id,
                'student_profile_id' => $studentProfile->student_profile_id,
                'attempt_number' => $submissionsUsed + 1,
                'status' => Submission::STATUS_SUBMITTED,
                'warning_count' => (int) ($validated['warning_count'] ?? 0),
                'submitted_at' => now(),
            ]);

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

                $submission->answers()->create([
                    'assessment_item_id' => $item->assessment_item_id,
                    'assessment_item_choice_id' => $choiceId,
                    'answer_text' => $answerText,
                ]);
            }
        });

        Log::info('Student submitted assessment.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'class_assessment_id' => $classAssessment->class_assessment_id,
            'assessment_id' => $classAssessment->assessment_id,
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

        if ($this->studentAssessmentStatus($classAssessment) !== 'available') {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'This assessment is not available to take right now.']);
        }

        return null;
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
