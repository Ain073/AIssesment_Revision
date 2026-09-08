<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Assessment;
use App\Models\PublishAssessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $validated = $request->validate([
            'class_keys' => ['required', 'array', 'min:1'],
            'class_keys.*' => ['required', 'uuid'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:available_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'warning_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'display_mode' => ['required', 'string', Rule::in([
                PublishAssessment::DISPLAY_ALL_QUESTIONS,
                PublishAssessment::DISPLAY_ONE_QUESTION,
            ])],
            'score_visibility' => ['nullable', 'boolean'],
            'answer_visibility' => ['nullable', 'boolean'],
            'prevent_copy_paste' => ['nullable', 'boolean'],
            'detect_tab_switch' => ['nullable', 'boolean'],
            'screenshot_protection' => ['nullable', 'boolean'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

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
            'class_keys' => ['required', 'array', 'min:1'],
            'class_keys.*' => ['required', 'uuid'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:available_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'warning_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'display_mode' => ['required', 'string', Rule::in([
                PublishAssessment::DISPLAY_ALL_QUESTIONS,
                PublishAssessment::DISPLAY_ONE_QUESTION,
            ])],
            'score_visibility' => ['nullable', 'boolean'],
            'answer_visibility' => ['nullable', 'boolean'],
            'prevent_copy_paste' => ['nullable', 'boolean'],
            'detect_tab_switch' => ['nullable', 'boolean'],
            'screenshot_protection' => ['nullable', 'boolean'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

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
}
