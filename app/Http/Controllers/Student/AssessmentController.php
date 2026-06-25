<?php

namespace App\Http\Controllers\Student;

use App\Models\ClassAssessment;
use App\Models\Submission;
use App\Models\SubmissionSecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
