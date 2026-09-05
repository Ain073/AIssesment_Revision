<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\PublishAssessment;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends BaseController
{
    public function assessments(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $handledSubjects = $this->handledSubjects($instructorProfile);
        $activeAssessmentTab = $request->query('tab') === 'published' ? 'published' : 'draft';
        $assessments = $instructorProfile
            ? $instructorProfile->assessments()
                ->with(['subject', 'items.choices', 'publishAssessments.class'])
                ->where('status', '!=', Assessment::STATUS_ARCHIVED)
                ->withCount([
                    'items',
                    'publishAssessments',
                    'publishAssessments as submissions_count' => function ($query): void {
                        $query->join('submissions', 'submissions.publish_assessment_id', '=', 'publish_assessment.publish_assessment_id');
                    },
                ])
                ->latest('assessment_id')
                ->get()
            : collect();
        $classesBySubject = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
                ->whereNull('classes.archived_at')
                ->orderBy('classes.year_level')
                ->orderBy('classes.section_name')
                ->get()
                ->groupBy('subject_id')
            : collect();
        $publishedAssessments = $instructorProfile
            ? PublishAssessment::query()
                ->with(['assessment.subject', 'class.subject'])
                ->withCount(['submissions as submitted_count' => fn ($query) => $query->where('status', Submission::STATUS_SUBMITTED)])
                ->whereHas('assessment', fn ($query) => $query->where('instructor_id', $instructorProfile->instructor_profile_id))
                ->latest('publish_assessment_id')
                ->get()
                ->each(function (PublishAssessment $publishAssessment) {
                    $publishAssessment->display_status = $publishAssessment->publish_status === PublishAssessment::STATUS_CLOSED
                        || ($publishAssessment->due_at && $publishAssessment->due_at->isPast())
                            ? 'completed'
                            : 'pending';
                })
            : collect();

        return view('instructor.assessments.index', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $handledSubjects,
            'assessments' => $assessments,
            'publishedAssessments' => $publishedAssessments,
            'activeAssessmentTab' => $activeAssessmentTab,
            'classesBySubject' => $classesBySubject,
            'assessmentTypes' => $this->assessmentTypes(),
            'itemTypes' => $this->itemTypes(),
        ]);
    }

    public function createAssessment(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        return view('instructor.assessments.create', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $this->handledSubjects($instructorProfile),
            'assessmentTypes' => $this->assessmentTypes(),
            'reportCategories' => $this->reportCategories(),
            'reportingTerms' => $this->reportingTerms(),
        ]);
    }

    public function showAssessment(Assessment $assessment): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        if ($ownedAssessment->publishAssessments()->exists()) {
            $editableAssessment = DB::transaction(function () use ($ownedAssessment): Assessment {
                $copy = $this->copyAssessment($ownedAssessment, Assessment::STATUS_DRAFT);
                $ownedAssessment->update(['status' => Assessment::STATUS_ARCHIVED]);

                return $copy;
            });

            return redirect()
                ->route('instructor.assessments.show', $editableAssessment)
                ->with('status', 'An editable draft copy was created. Published assessment records remain unchanged.');
        }

        $ownedAssessment->load([
            'subject',
            'items.choices',
            'publishAssessments.class',
        ])->loadCount('items', 'publishAssessments');

        $publishableClasses = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
                ->whereHas('contextDetail', fn ($query) => $query->where('subject_id', $ownedAssessment->subject_id))
                ->whereNull('classes.archived_at')
                ->orderBy('classes.year_level')
                ->orderBy('classes.section_name')
                ->get()
            : collect();

        return view('instructor.assessments.show', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedAssessment,
            'publishableClasses' => $publishableClasses,
            'hasStudentSubmissions' => $ownedAssessment->publishAssessments()
                ->whereHas('submissions')
                ->exists(),
            'assessmentTypes' => $this->assessmentTypes(),
            'itemTypes' => $this->itemTypes(),
            'reportCategories' => $this->reportCategories(),
            'reportingTerms' => $this->reportingTerms(),
        ]);
    }

    public function storeAssessment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating assessments.');

        $handledSubjectIds = $this->handledSubjects($instructorProfile)->pluck('subject_id')->all();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($handledSubjectIds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(array_keys($this->assessmentTypes()))],
            'report_category' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'reporting_term' => ['required', 'string', Rule::in(array_keys($this->reportingTerms()))],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $assessment = $instructorProfile->assessments()->create($validated + [
            'status' => Assessment::STATUS_DRAFT,
        ]);

        Log::info('Assessment created by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessment->assessment_id,
            'subject_id' => $assessment->subject_id,
        ]);

        return redirect()
            ->route('instructor.assessments.show', $assessment)
            ->with('status', 'Assessment saved as draft. You can now add items.');
    }

    public function updateAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(array_keys($this->assessmentTypes()))],
            'report_category' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'reporting_term' => ['required', 'string', Rule::in(array_keys($this->reportingTerms()))],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $ownedAssessment->update($validated);

        Log::info('Assessment details updated by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
        ]);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Assessment details updated.');
    }

    public function destroyAssessment(Request $request, Assessment $assessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $hasClassAssignments = $ownedAssessment->publishAssessments()->exists();

        $assessmentId = $ownedAssessment->assessment_id;
        $assessmentTitle = $ownedAssessment->title;

        if ($hasClassAssignments) {
            $ownedAssessment->update(['status' => Assessment::STATUS_ARCHIVED]);

            Log::info('Assessment archived by instructor.', [
                'actor_id' => $user->id,
                'assessment_id' => $assessmentId,
                'assessment_title' => $assessmentTitle,
                'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Assessment removed from Draft / Stored. Published records remain available.']);
            }

            return redirect()
                ->route('instructor.assessments', ['tab' => 'draft'])
                ->with('status', 'Assessment removed from Draft / Stored. Published records remain available.');
        }

        DB::transaction(function () use ($ownedAssessment) {
            $ownedAssessment->items()->delete();
            $ownedAssessment->delete();
        });

        Log::warning('Assessment deleted by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessmentId,
            'assessment_title' => $assessmentTitle,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Assessment deleted successfully.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'draft'])
            ->with('status', 'Assessment deleted successfully.');
    }

    public function destroyPublishedAssessment(Request $request, PublishAssessment $publishAssessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedPublishAssessment = $this->ownedPublishAssessment($publishAssessment, $instructorProfile);

        $ownedPublishAssessment->loadMissing(['assessment', 'class']);

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

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Published assessment deleted. You can publish the stored assessment again.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'published'])
            ->with('status', 'Published assessment deleted. You can publish the stored assessment again.');
    }

    public function storeAssessmentItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $validated = $request->validate([
            'item_type' => ['required', 'string', Rule::in(array_keys($this->itemTypes()))],
            'points' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_type' => ['required', 'string', Rule::in(array_keys($this->itemTypes()))],
            'items.*.question_text' => ['required', 'string', 'max:4000'],
            'items.*.points' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'items.*.choices' => ['nullable', 'array', 'max:6'],
            'items.*.choices.*' => ['nullable', 'string', 'max:1000'],
            'items.*.correct_choice' => ['nullable', 'integer', 'min:0', 'max:5'],
            'items.*.true_false_answer' => ['nullable', Rule::in(['true', 'false'])],
            'items.*.accepted_answer' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['items'] as $index => $itemData) {
            $itemType = $itemData['item_type'];
            $choices = collect($itemData['choices'] ?? [])
                ->map(fn ($choice) => trim((string) $choice))
                ->filter()
                ->values();

            if ($itemType === 'multiple_choice' && $choices->count() < 2) {
                throw ValidationException::withMessages([
                    "items.{$index}.choices" => 'Multiple choice items need at least two choices.',
                ]);
            }

            if ($itemType === 'multiple_choice' && ! $choices->has((int) ($itemData['correct_choice'] ?? -1))) {
                throw ValidationException::withMessages([
                    "items.{$index}.correct_choice" => 'Please select the correct choice.',
                ]);
            }

            if ($itemType === 'true_false' && empty($itemData['true_false_answer'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.true_false_answer" => 'Please select True or False as the correct answer.',
                ]);
            }

            if ($itemType === 'identification' && blank($itemData['accepted_answer'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.accepted_answer" => 'Please enter the accepted answer for identification.',
                ]);
            }
        }

        DB::transaction(function () use ($ownedAssessment, $validated) {
            $nextOrder = ((int) $ownedAssessment->items()->max('sort_order')) + 1;

            foreach ($validated['items'] as $itemData) {
                $itemType = $itemData['item_type'];

                $item = $ownedAssessment->items()->create([
                    'question_text' => $itemData['question_text'],
                    'item_type' => $itemType,
                    'points' => $itemData['points'],
                    'is_required' => true,
                    'sort_order' => $nextOrder,
                ]);

                $nextOrder++;

                if ($itemType === 'multiple_choice') {
                    $choices = collect($itemData['choices'] ?? [])
                        ->map(fn ($choice) => trim((string) $choice))
                        ->filter()
                        ->values();
                    $correctChoice = (int) ($itemData['correct_choice'] ?? -1);

                    foreach ($choices as $index => $choiceText) {
                        $item->choices()->create([
                            'choice_text' => $choiceText,
                            'is_correct' => $index === $correctChoice,
                            'sort_order' => $index + 1,
                        ]);
                    }
                }

                if ($itemType === 'true_false') {
                    foreach (['true' => 'True', 'false' => 'False'] as $value => $label) {
                        $item->choices()->create([
                            'choice_text' => $label,
                            'is_correct' => ($itemData['true_false_answer'] ?? null) === $value,
                            'sort_order' => $value === 'true' ? 1 : 2,
                        ]);
                    }
                }

                if ($itemType === 'identification') {
                    $item->choices()->create([
                        'choice_text' => trim((string) $itemData['accepted_answer']),
                        'is_correct' => true,
                        'sort_order' => 1,
                    ]);
                }
            }
        });

        Log::info('Assessment items added by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'item_types' => collect($validated['items'])->pluck('item_type')->unique()->values()->all(),
            'item_count' => count($validated['items']),
        ]);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', count($validated['items']).' question'.(count($validated['items']) === 1 ? '' : 's').' added.');
    }

    public function updateAssessmentItem(Request $request, Assessment $assessment, AssessmentItem $item): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);
        $ownedItem = $this->ownedAssessmentItem($ownedAssessment, $item);

        $this->ensureAssessmentHasNoSubmissions($ownedAssessment, 'Questions cannot be edited after students have submitted attempts.');

        $validated = $this->validateAssessmentItemUpdate($request, $ownedItem);

        DB::transaction(function () use ($ownedItem, $validated): void {
            $ownedItem->update([
                'question_text' => $validated['question_text'],
                'points' => $validated['points'],
            ]);

            $this->syncItemChoices($ownedItem, $validated);
        });

        Log::info('Assessment item updated by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'assessment_item_id' => $ownedItem->assessment_item_id,
        ]);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Question updated successfully.');
    }

    public function destroyAssessmentItem(Request $request, Assessment $assessment, AssessmentItem $item): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);
        $ownedItem = $this->ownedAssessmentItem($ownedAssessment, $item);

        $this->ensureAssessmentHasNoSubmissions($ownedAssessment, 'Questions cannot be deleted after students have submitted attempts.');

        DB::transaction(function () use ($ownedAssessment, $ownedItem): void {
            $ownedItem->delete();

            $ownedAssessment->items()
                ->orderBy('sort_order')
                ->get()
                ->values()
                ->each(function (AssessmentItem $item, int $index): void {
                    $item->update(['sort_order' => $index + 1]);
                });
        });

        Log::warning('Assessment item deleted by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'assessment_item_id' => $ownedItem->assessment_item_id,
        ]);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Question deleted successfully.');
    }

    private function ownedAssessmentItem(Assessment $assessment, AssessmentItem $item): AssessmentItem
    {
        abort_unless(
            $item->assessment_id === $assessment->assessment_id,
            404,
            'Question not found under this assessment.'
        );

        return $item->loadMissing('choices');
    }

    private function ensureAssessmentHasNoSubmissions(Assessment $assessment, string $message): void
    {
        if ($assessment->publishAssessments()->whereHas('submissions')->exists()) {
            throw ValidationException::withMessages([
                'assessment' => $message,
            ]);
        }
    }

    private function validateAssessmentItemUpdate(Request $request, AssessmentItem $item): array
    {
        $validated = $request->validate([
            'question_text' => ['required', 'string', 'max:4000'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'choices' => ['nullable', 'array', 'max:6'],
            'choices.*' => ['nullable', 'string', 'max:1000'],
            'correct_choice' => ['nullable', 'integer', 'min:0', 'max:5'],
            'true_false_answer' => ['nullable', Rule::in(['true', 'false'])],
            'accepted_answer' => ['nullable', 'string', 'max:1000'],
        ]);

        $choices = collect($validated['choices'] ?? [])
            ->map(fn ($choice) => trim((string) $choice))
            ->filter()
            ->values();

        if ($item->item_type === 'multiple_choice' && $choices->count() < 2) {
            throw ValidationException::withMessages([
                'choices' => 'Multiple choice items need at least two choices.',
            ]);
        }

        if ($item->item_type === 'multiple_choice' && ! $choices->has((int) ($validated['correct_choice'] ?? -1))) {
            throw ValidationException::withMessages([
                'correct_choice' => 'Please select the correct choice.',
            ]);
        }

        if ($item->item_type === 'true_false' && empty($validated['true_false_answer'])) {
            throw ValidationException::withMessages([
                'true_false_answer' => 'Please select True or False as the correct answer.',
            ]);
        }

        if ($item->item_type === 'identification' && blank($validated['accepted_answer'] ?? null)) {
            throw ValidationException::withMessages([
                'accepted_answer' => 'Please enter the accepted answer for identification.',
            ]);
        }

        $validated['choices'] = $choices->all();

        return $validated;
    }

    private function syncItemChoices(AssessmentItem $item, array $validated): void
    {
        $item->choices()->delete();

        if ($item->item_type === 'multiple_choice') {
            foreach ($validated['choices'] as $index => $choiceText) {
                $item->choices()->create([
                    'choice_text' => $choiceText,
                    'is_correct' => $index === (int) $validated['correct_choice'],
                    'sort_order' => $index + 1,
                ]);
            }

            return;
        }

        if ($item->item_type === 'true_false') {
            foreach (['true' => 'True', 'false' => 'False'] as $value => $label) {
                $item->choices()->create([
                    'choice_text' => $label,
                    'is_correct' => ($validated['true_false_answer'] ?? null) === $value,
                    'sort_order' => $value === 'true' ? 1 : 2,
                ]);
            }

            return;
        }

        if ($item->item_type === 'identification') {
            $item->choices()->create([
                'choice_text' => trim((string) $validated['accepted_answer']),
                'is_correct' => true,
                'sort_order' => 1,
            ]);
        }
    }
}
