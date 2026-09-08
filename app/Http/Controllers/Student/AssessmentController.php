<?php

namespace App\Http\Controllers\Student;

use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Models\SubmissionSecurityEvent;
use App\Services\NotificationService;
use App\Support\AssessmentScoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends BaseController
{
    public function assessments(): View
    {
        $user = $this->currentUser();

        return view('student.assessments.index', $this->sharedData($user, 'assessments') + $this->studentAssessmentsData($user));
    }

    public function assessmentsLive(): View
    {
        return view('student.assessments.assessment-list', $this->studentAssessmentsData($this->currentUser()));
    }

    public function takeAssessment(PublishAssessment $publishAssessment): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($publishAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        Log::info('Student opened published assessment.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'publish_assessment_id' => $publishAssessment->publish_assessment_id,
            'assessment_id' => $publishAssessment->assessment_id,
            'class_id' => $publishAssessment->class_id,
        ]);

        return view('student.assessments.take', $this->sharedData($user, 'assessments') + [
            'publishAssessment' => $publishAssessment,
            'assessment' => $publishAssessment->assessment,
            'class' => $publishAssessment->class,
            'warningLimit' => $this->warningLimit($publishAssessment),
        ]);
    }

    public function submittedAssessment(PublishAssessment $publishAssessment): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $this->ensurePublishedAssessmentEnrollment($publishAssessment, $studentProfile);

        $submission = Submission::query()
            ->with(['answers.choice'])
            ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_SUBMITTED)
            ->latest('submitted_at')
            ->first();

        if (! $submission) {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'No submitted attempt was found for this assessment.']);
        }

        $items = $publishAssessment->assessment?->items ?? collect();
        $maxScore = (float) $items->sum(fn ($item): float => (float) $item->points);
        $score = $this->submissionScore($submission, $items);
        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;
        $passingScore = $maxScore * 0.75;

        return view('student.assessments.submitted', $this->sharedData($user, 'assessments') + [
            'publishAssessment' => $publishAssessment,
            'assessment' => $publishAssessment->assessment,
            'class' => $publishAssessment->class,
            'submission' => $submission,
            'showScore' => (bool) $publishAssessment->score_visibility,
            'showAnswers' => (bool) $publishAssessment->answer_visibility,
            'score' => $score,
            'scoreText' => $this->formatNumber($score),
            'maxScore' => $maxScore,
            'maxScoreText' => $this->formatNumber($maxScore),
            'percentage' => $percentage,
            'passed' => $maxScore > 0 && $score >= $passingScore,
            'answerRows' => $this->answerRows($submission, $items),
        ]);
    }

    public function startAssessment(PublishAssessment $publishAssessment): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($publishAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        if ($publishAssessment->assessment->items->isEmpty()) {
            return redirect()
                ->route('student.assessments.take', $publishAssessment)
                ->withErrors(['assessment' => 'This assessment has no questions yet.']);
        }

        $submission = $this->startOrResumeSubmission($publishAssessment, $studentProfile);

        if ($submission instanceof RedirectResponse) {
            return $submission;
        }

        $items = $publishAssessment->assessment->items;

        if ($publishAssessment->shuffle_items) {
            $items = $items->shuffle()->values();
        }

        if ($publishAssessment->shuffle_choices) {
            $items->each(function ($item) {
                $item->setRelation('choices', $item->choices->shuffle()->values());
            });
        }

        Log::info('Student started assessment attempt view.', [
            'actor_id' => $user->id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'publish_assessment_id' => $publishAssessment->publish_assessment_id,
            'assessment_id' => $publishAssessment->assessment_id,
            'class_id' => $publishAssessment->class_id,
        ]);

        return view('student.assessments.attempt', [
            'user' => $user,
            'studentProfile' => $studentProfile,
            'publishAssessment' => $publishAssessment,
            'assessment' => $publishAssessment->assessment,
            'class' => $publishAssessment->class,
            'items' => $items,
            'submission' => $submission,
            'warningLimit' => $this->warningLimit($publishAssessment),
        ]);
    }

    public function recordSecurityEvent(Request $request, PublishAssessment $publishAssessment): JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        abort_if($studentProfile instanceof RedirectResponse, 403, 'A student profile is required.');

        $this->ensurePublishedAssessmentEnrollment($publishAssessment, $studentProfile);

        $validated = $request->validate([
            'event_uuid' => ['required', 'string', 'max:64'],
            'event_type' => ['required', 'string', Rule::in(array_keys(SubmissionSecurityEvent::labels()))],
        ]);

        if (! $this->securityEventEnabled($publishAssessment, $validated['event_type'])) {
            throw ValidationException::withMessages([
                'event_type' => 'This security event is not enabled for the assessment.',
            ]);
        }

        $result = DB::transaction(function () use ($request, $publishAssessment, $studentProfile, $validated): array {
            $submission = Submission::query()
                ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
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

            $warningLimit = $this->warningLimit($publishAssessment);

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
                'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                'submission_id' => $result['submission']->submission_id,
            ]);

            $publishAssessment->loadMissing('assessment', 'classDetail.class.instructorProfile.user');

            if ($publishAssessment->class?->instructorProfile?->user) {
                app(NotificationService::class)->send(
                    $publishAssessment->class->instructorProfile->user,
                    'Security Limit Reached',
                    $user->displayName().' reached the warning limit in '.$publishAssessment->assessment?->title.'.',
                    route('instructor.assessments.results', $publishAssessment),
                    'warning'
                );
            }
        }

        return response()->json([
            'recorded' => $result['recorded'],
            'warning_count' => $result['submission']->warning_count,
            'warning_limit' => $result['warning_limit'],
            'should_auto_submit' => $result['should_auto_submit'],
        ]);
    }

    public function submitAssessment(Request $request, PublishAssessment $publishAssessment): RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $unavailable = $this->prepareAccessibleAssessment($publishAssessment, $studentProfile);

        if ($unavailable instanceof RedirectResponse) {
            return $unavailable;
        }

        $publishAssessment->load(['assessment.items.choices', 'classDetail.class']);

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
        ]);

        $answers = collect($validated['answers'] ?? []);
        $items = $publishAssessment->assessment->items;

        $submission = Submission::query()
            ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->where('status', Submission::STATUS_IN_PROGRESS)
            ->latest('attempt_number')
            ->first();

        if (! $submission) {
            return redirect()
                ->route('student.assessments')
                ->withErrors(['assessment' => 'No active attempt was found. Start the assessment before submitting.']);
        }

        DB::transaction(function () use ($publishAssessment, $items, $answers, $submission) {
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

            $warningLimit = $this->warningLimit($publishAssessment);
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
            'publish_assessment_id' => $publishAssessment->publish_assessment_id,
            'assessment_id' => $publishAssessment->assessment_id,
            'submission_id' => $submission->submission_id,
            'warning_count' => $submission->fresh()->warning_count,
        ]);

        $publishAssessment->loadMissing('assessment', 'classDetail.class.instructorProfile.user');

        if ($publishAssessment->class?->instructorProfile?->user) {
            app(NotificationService::class)->send(
                $publishAssessment->class->instructorProfile->user,
                'Assessment Submitted',
                $user->displayName().' submitted '.$publishAssessment->assessment?->title.'.',
                route('instructor.assessments.results', $publishAssessment),
                'assessment'
            );
        }

        return redirect()
            ->route('student.assessments.submitted', $publishAssessment);
    }

    private function answerRows(Submission $submission, Collection $items): Collection
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return $items
            ->map(function ($item) use ($answers): array {
                $answer = $answers->get($item->assessment_item_id);
                $earnedPoints = AssessmentScoring::earnedPoints($item, $answer);

                return [
                    'item' => $item,
                    'answer' => $answer,
                    'student_answer' => $answer ? $this->studentAnswerText($answer) : 'No answer',
                    'correct_answer' => $this->correctAnswerText($item),
                    'is_correct' => AssessmentScoring::isCorrect($item, $answer),
                    'earned_points' => $earnedPoints,
                ];
            })
            ->values();
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
    }

    private function studentAnswerText($answer): string
    {
        if ($answer->choice) {
            return (string) $answer->choice->choice_text;
        }

        if (filled($answer->answer_text)) {
            return (string) $answer->answer_text;
        }

        return 'No answer';
    }

    private function correctAnswerText($item): string
    {
        $correctAnswers = $item->choices
            ->where('is_correct', true)
            ->pluck('choice_text')
            ->filter()
            ->values();

        return $correctAnswers->isNotEmpty()
            ? $correctAnswers->join(', ')
            : 'For teacher checking';
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
