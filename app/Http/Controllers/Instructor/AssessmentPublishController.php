<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Assessment;
use App\Models\PublishAssessment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentPublishController extends BaseController
{
    public function publishAssessmentForm(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $handledSubjects = $this->handledSubjects($instructorProfile);
        $assessments = $instructorProfile
            ? $instructorProfile->assessments()
                ->with('subject')
                ->where('status', '!=', Assessment::STATUS_ARCHIVED)
                ->withCount('items')
                ->orderBy('title')
                ->get()
            : collect();
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
                ->whereNull('classes.archived_at')
                ->orderBy('classes.year_level')
                ->orderBy('classes.section_name')
                ->get()
                ->each(fn ($class) => $class->applyEnrolledStudentsCount())
            : collect();

        $selectedAssessment = $assessments->firstWhere('public_id', $request->query('assessment_key'));
        $selectedSubjectId = (int) old(
            'subject_id',
            $selectedAssessment?->subject_id ?? $request->query('subject_id')
        );

        return view('instructor.assessments.publish', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $handledSubjects,
            'assessments' => $assessments,
            'classes' => $classes,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedAssessmentKey' => (string) old('assessment_key', $selectedAssessment?->public_id),
        ]);
    }

    public function publishAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        if ($ownedAssessment->status === Assessment::STATUS_ARCHIVED) {
            return redirect()
                ->route('instructor.assessments', ['tab' => 'draft'])
                ->withErrors(['publish' => 'Published assessment records cannot be republished. Use the editable draft template instead.']);
        }

        if (! $ownedAssessment->items()->exists()) {
            return redirect()
                ->route('instructor.assessments.show', $ownedAssessment)
                ->withErrors(['publish' => 'Add at least one item before publishing this assessment.']);
        }

        $validated = $request->validate($this->publishRules(), $this->publishScheduleValidationMessages());
        $validated['question_time_limit_seconds'] = $validated['display_mode'] === PublishAssessment::DISPLAY_ONE_QUESTION
            ? (int) $validated['question_time_limit_seconds']
            : null;

        $this->publishAssessmentToClasses($request, $user, $instructorProfile, $ownedAssessment, $validated);

        return redirect()
            ->route('instructor.assessments', ['tab' => 'published'])
            ->with('status', 'Assessment published to selected classes.');
    }

    public function publishSelectedAssessment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before publishing assessments.');

        $handledSubjectIds = $this->handledSubjects($instructorProfile)->pluck('subject_id')->all();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($handledSubjectIds)],
            'assessment_key' => ['required', 'uuid'],
        ] + $this->publishRules(), $this->publishScheduleValidationMessages());
        $validated['question_time_limit_seconds'] = $validated['display_mode'] === PublishAssessment::DISPLAY_ONE_QUESTION
            ? (int) $validated['question_time_limit_seconds']
            : null;

        $ownedAssessment = $instructorProfile->assessments()
            ->where('subject_id', $validated['subject_id'])
            ->where('status', '!=', Assessment::STATUS_ARCHIVED)
            ->where('public_id', $validated['assessment_key'])
            ->first();

        if (! $ownedAssessment) {
            throw ValidationException::withMessages([
                'assessment_key' => 'Select one of your assessments under the chosen subject.',
            ]);
        }

        if (! $ownedAssessment->items()->exists()) {
            throw ValidationException::withMessages([
                'assessment_key' => 'Add at least one item before publishing this assessment.',
            ]);
        }

        $this->publishAssessmentToClasses($request, $user, $instructorProfile, $ownedAssessment, $validated);

        return redirect()
            ->route('instructor.assessments', ['tab' => 'published'])
            ->with('status', 'Assessment published to selected classes.');
    }

    private function publishRules(): array
    {
        $currentDateTimeFloor = $this->currentDateTimeFloor();

        return [
            'class_keys' => ['required', 'array', 'min:1'],
            'class_keys.*' => ['required', 'uuid'],
            'available_at' => ['nullable', 'date', 'after_or_equal:'.$currentDateTimeFloor],
            'due_at' => ['nullable', 'date', 'after_or_equal:'.$currentDateTimeFloor, 'after:available_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'warning_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'display_mode' => ['required', 'string', Rule::in([
                PublishAssessment::DISPLAY_ALL_QUESTIONS,
                PublishAssessment::DISPLAY_ONE_QUESTION,
            ])],
            'question_time_limit_seconds' => ['nullable', 'required_if:display_mode,'.PublishAssessment::DISPLAY_ONE_QUESTION, 'integer', 'min:5', 'max:3600'],
            'score_visibility' => ['nullable', 'boolean'],
            'answer_visibility' => ['nullable', 'boolean'],
            'prevent_copy_paste' => ['nullable', 'boolean'],
            'detect_tab_switch' => ['nullable', 'boolean'],
            'screenshot_protection' => ['nullable', 'boolean'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ];
    }

    private function publishScheduleValidationMessages(): array
    {
        return [
            'available_at.date' => 'Enter a valid available date and time.',
            'available_at.after_or_equal' => 'The available date and time cannot be in the past.',
            'due_at.date' => 'Enter a valid due date and time.',
            'due_at.after_or_equal' => 'The due date and time cannot be in the past.',
            'due_at.after' => 'Set the due date and time after the available date and time.',
            'question_time_limit_seconds.required_if' => 'Enter the seconds per question for one-question mode.',
            'question_time_limit_seconds.integer' => 'Enter a whole number of seconds per question.',
            'question_time_limit_seconds.min' => 'Question time must be at least 5 seconds.',
            'question_time_limit_seconds.max' => 'Question time cannot exceed 3600 seconds.',
        ];
    }

    private function currentDateTimeFloor(): string
    {
        return now()->startOfMinute()->toDateTimeString();
    }

    public function destroyPublishedAssessment(Request $request, PublishAssessment $publishAssessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedPublishAssessment = $this->ownedPublishAssessment($publishAssessment, $instructorProfile);

        $ownedPublishAssessment->loadMissing(['assessment', 'classDetail.class']);

        $assessment = $ownedPublishAssessment->assessment;
        $assessmentId = $assessment?->assessment_id;
        $assessmentTitle = $assessment?->title ?? 'Untitled Assessment';
        $className = $ownedPublishAssessment->class?->class_name ?? 'class';
        $publishAssessmentId = $ownedPublishAssessment->publish_assessment_id;
        $submissionCount = $ownedPublishAssessment->submissions()->count();

        DB::transaction(function () use ($ownedPublishAssessment, $assessment): void {
            $ownedPublishAssessment->delete();

            if (
                $assessment
                && $assessment->status === Assessment::STATUS_ARCHIVED
                && ! $assessment->publishAssessments()->exists()
            ) {
                $assessment->items()->delete();
                $assessment->delete();
            }
        });

        Log::warning('Published assessment deleted by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessmentId,
            'assessment_title' => $assessmentTitle,
            'publish_assessment_id' => $publishAssessmentId,
            'class_name' => $className,
            'submission_count' => $submissionCount,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        AuditLogger::log('DELETE', 'Assessments', "Deleted published assessment '{$assessmentTitle}' from class {$className} ({$submissionCount} submission/s)");

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Published assessment deleted. You can publish the stored assessment again.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'published'])
            ->with('status', 'Published assessment deleted. You can publish the stored assessment again.');
    }

    public function reopenPublishedAssessment(Request $request, PublishAssessment $publishAssessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedPublishAssessment = $this->ownedPublishAssessment($publishAssessment, $instructorProfile);

        $isCompleted = $ownedPublishAssessment->publish_status === PublishAssessment::STATUS_CLOSED
            || ($ownedPublishAssessment->due_at && $ownedPublishAssessment->due_at->isPast());

        if (! $isCompleted) {
            throw ValidationException::withMessages([
                'due_at' => 'Only completed assessments can be republished.',
            ]);
        }

        $currentDateTimeFloor = now()->startOfMinute()->toDateTimeString();

        $validated = $request->validate([
            'available_at' => ['nullable', 'date', 'after_or_equal:'.$currentDateTimeFloor, 'before:due_at'],
            'due_at' => ['required', 'date', 'after_or_equal:'.$currentDateTimeFloor],
        ], [
            'available_at.date' => 'Enter a valid available date and time.',
            'available_at.after_or_equal' => 'The available date and time cannot be in the past.',
            'available_at.before' => 'The available date and time must be earlier than the due date and time.',
            'due_at.required' => 'Enter a new due date before republishing.',
            'due_at.date' => 'Enter a valid due date and time.',
            'due_at.after_or_equal' => 'The due date and time cannot be in the past.',
        ]);

        $previousDueAt = $ownedPublishAssessment->due_at;

        $ownedPublishAssessment->update([
            'available_at' => $validated['available_at'] ?? now(),
            'due_at' => $validated['due_at'],
            'original_due_at' => $ownedPublishAssessment->original_due_at ?? $previousDueAt,
            'reopened_at' => now(),
            'publish_status' => PublishAssessment::STATUS_PUBLISHED,
        ]);

        Log::info('Published assessment republished by instructor.', [
            'actor_id' => $user->id,
            'publish_assessment_id' => $ownedPublishAssessment->publish_assessment_id,
            'assessment_id' => $ownedPublishAssessment->assessment_id,
            'previous_due_at' => $previousDueAt?->toDateTimeString(),
            'new_due_at' => $ownedPublishAssessment->fresh()->due_at?->toDateTimeString(),
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        AuditLogger::log('REOPEN', 'Assessments', "Reopened published assessment with new due date: {$validated['due_at']}");

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Assessment republished for students who have not submitted yet.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'published'])
            ->with('status', 'Assessment republished for students who have not submitted yet.');
    }
}
