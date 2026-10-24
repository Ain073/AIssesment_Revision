<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentItemController extends BaseController
{
    public function storeAssessmentItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);
        $this->ensureAssessmentIsEditable($ownedAssessment, 'Published assessment records cannot be changed.');

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
            'items.*.accepted_answers' => ['nullable', 'array', 'max:10'],
            'items.*.accepted_answers.*' => ['nullable', 'string', 'max:1000'],
            'items.*.enum_answers' => ['nullable', 'array', 'min:1', 'max:20'],
            'items.*.enum_answers.*' => ['nullable', 'string', 'max:500'],
            'items.*.order_sensitive' => ['nullable', 'in:1,0,true,false'],
        ]);

        $itemErrors = [];
        $questionNumber = (int) $ownedAssessment->items()->max('sort_order');

        foreach ($validated['items'] as $index => $itemData) {
            $questionNumber++;
            $itemType = $itemData['item_type'];
            $choices = collect($itemData['choices'] ?? [])
                ->map(fn ($choice) => trim((string) $choice));
            $filledChoices = $choices->filter(fn ($choice) => $choice !== '');

            if ($itemType === 'multiple_choice' && $filledChoices->count() < 2) {
                $itemErrors["items.{$index}.choices"] = "Question {$questionNumber}: Multiple choice items need at least two choices.";
            }

            if ($itemType === 'multiple_choice') {
                $correctChoice = $itemData['correct_choice'] ?? null;
                $correctChoiceIndex = $correctChoice === null ? null : (int) $correctChoice;

                if ($correctChoiceIndex === null || ! $choices->has($correctChoiceIndex) || $choices->get($correctChoiceIndex) === '') {
                    $itemErrors["items.{$index}.correct_choice"] = "Question {$questionNumber}: Please select a filled correct choice.";
                }
            }

            if ($itemType === 'true_false' && empty($itemData['true_false_answer'])) {
                $itemErrors["items.{$index}.true_false_answer"] = "Question {$questionNumber}: Please select True or False as the correct answer.";
            }

            if ($itemType === 'identification') {
                $identAnswers = collect($itemData['accepted_answers'] ?? [])
                    ->concat([$itemData['accepted_answer'] ?? null])
                    ->map(fn ($a) => trim((string) $a))
                    ->filter(fn ($a) => $a !== '');

                if ($identAnswers->isEmpty()) {
                    $itemErrors["items.{$index}.accepted_answer"] = "Question {$questionNumber}: Please enter at least one accepted answer for identification.";
                }
            }

            if ($itemType === 'enumeration') {
                $filledEnumAnswers = collect($itemData['enum_answers'] ?? [])
                    ->map(fn ($a) => trim((string) $a))
                    ->filter(fn ($a) => $a !== '');

                if ($filledEnumAnswers->count() < 1) {
                    $itemErrors["items.{$index}.enum_answers"] = "Question {$questionNumber}: Enter at least one accepted answer for enumeration.";
                }
            }
        }

        if ($itemErrors !== []) {
            throw ValidationException::withMessages($itemErrors);
        }

        DB::transaction(function () use ($ownedAssessment, $validated) {
            $nextOrder = ((int) $ownedAssessment->items()->max('sort_order')) + 1;

            foreach ($validated['items'] as $itemData) {
                $itemType = $itemData['item_type'];
                $itemPoints = $itemData['points'];

                if ($itemType === 'enumeration') {
                    $enumCount = count(array_filter(
                        array_map('trim', (array) ($itemData['enum_answers'] ?? [])),
                        fn ($a) => $a !== ''
                    ));
                    $itemPoints = max(1, $enumCount);
                }

                $item = $ownedAssessment->items()->create([
                    'question_text' => $itemData['question_text'],
                    'item_type' => $itemType,
                    'points' => $itemPoints,
                    'is_required' => true,
                    'sort_order' => $nextOrder,
                ]);

                $nextOrder++;

                if ($itemType === 'multiple_choice') {
                    $choices = collect($itemData['choices'] ?? [])
                        ->map(fn ($choice) => trim((string) $choice))
                        ->filter(fn ($choice) => $choice !== '');
                    $correctChoice = (int) ($itemData['correct_choice'] ?? -1);
                    $sortOrder = 1;

                    foreach ($choices as $choiceIndex => $choiceText) {
                        $item->choices()->create([
                            'choice_text' => $choiceText,
                            'is_correct' => (int) $choiceIndex === $correctChoice,
                            'sort_order' => $sortOrder,
                        ]);

                        $sortOrder++;
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
                    $identAnswers = collect($itemData['accepted_answers'] ?? [])
                        ->concat([$itemData['accepted_answer'] ?? null])
                        ->map(fn ($a) => trim((string) $a))
                        ->filter(fn ($a) => $a !== '')
                        ->unique(fn ($a) => strtolower($a))
                        ->values();

                    foreach ($identAnswers as $i => $answerText) {
                        $item->choices()->create([
                            'choice_text' => $answerText,
                            'is_correct' => true,
                            'sort_order' => $i + 1,
                        ]);
                    }
                }

                if ($itemType === 'enumeration') {
                    $orderSensitive = filter_var($itemData['order_sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $item->update(['order_sensitive' => $orderSensitive]);
                    $enumAnswers = collect($itemData['enum_answers'] ?? [])
                        ->map(fn ($a) => trim((string) $a))
                        ->filter(fn ($a) => $a !== '')
                        ->values();

                    foreach ($enumAnswers as $i => $answerText) {
                        $item->choices()->create([
                            'choice_text' => $answerText,
                            'is_correct' => true,
                            'sort_order' => $i + 1,
                        ]);
                    }
                }
            }
        });

        Log::info('Assessment items added by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'item_types' => collect($validated['items'])->pluck('item_type')->unique()->values()->all(),
            'item_count' => count($validated['items']),
        ]);

        AuditLogger::log('ADD_ITEM', 'Assessments', count($validated['items']).' question/s added to assessment \''.$ownedAssessment->title."'", $ownedAssessment);

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

        $this->ensureAssessmentIsEditable($ownedAssessment, 'Published assessment records cannot be edited.');
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

        AuditLogger::log('UPDATE', 'Assessments', "Updated question #{$ownedItem->sort_order} in assessment '{$ownedAssessment->title}'", $ownedItem);

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

        $this->ensureAssessmentIsEditable($ownedAssessment, 'Published assessment records cannot be deleted.');
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

        AuditLogger::log('DELETE', 'Assessments', "Deleted question #{$ownedItem->sort_order} from assessment '{$ownedAssessment->title}'", $ownedAssessment);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Question deleted successfully.');
    }

    private function ownedAssessmentItem(Assessment $assessment, AssessmentItem $item): AssessmentItem
    {
        if ($item->assessment_id !== $assessment->assessment_id) {
            throw ValidationException::withMessages([
                'item' => 'Question not found under this assessment.',
            ]);
        }

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
            'accepted_answers' => ['nullable', 'array', 'max:10'],
            'accepted_answers.*' => ['nullable', 'string', 'max:1000'],
            'enum_answers' => ['nullable', 'array', 'min:1', 'max:20'],
            'enum_answers.*' => ['nullable', 'string', 'max:500'],
            'order_sensitive' => ['nullable', 'in:1,0,true,false'],
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

        if ($item->item_type === 'identification') {
            $identAnswers = collect($request->input('accepted_answers', []))
                ->concat([$request->input('accepted_answer')])
                ->map(fn ($a) => trim((string) $a))
                ->filter(fn ($a) => $a !== '')
                ->unique(fn ($a) => strtolower($a))
                ->values();

            if ($identAnswers->isEmpty()) {
                throw ValidationException::withMessages([
                    'accepted_answer' => 'Please enter at least one accepted answer for identification.',
                ]);
            }

            $validated['accepted_answers'] = $identAnswers->all();
        }

        if ($item->item_type === 'enumeration') {
            $filledEnumAnswers = collect($validated['enum_answers'] ?? [])
                ->map(fn ($a) => trim((string) $a))
                ->filter(fn ($a) => $a !== '');

            if ($filledEnumAnswers->count() < 1) {
                throw ValidationException::withMessages([
                    'enum_answers' => 'Enter at least one accepted answer for enumeration.',
                ]);
            }

            $validated['enum_answers'] = $filledEnumAnswers->values()->all();
            $validated['points'] = $filledEnumAnswers->count();
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
            $identAnswers = collect($validated['accepted_answers'] ?? [])
                ->concat([$validated['accepted_answer'] ?? null])
                ->map(fn ($a) => trim((string) $a))
                ->filter(fn ($a) => $a !== '')
                ->unique(fn ($a) => strtolower($a))
                ->values();

            foreach ($identAnswers as $i => $answerText) {
                $item->choices()->create([
                    'choice_text' => $answerText,
                    'is_correct' => true,
                    'sort_order' => $i + 1,
                ]);
            }

            return;
        }

        if ($item->item_type === 'enumeration') {
            $orderSensitive = filter_var($validated['order_sensitive'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $item->update(['order_sensitive' => $orderSensitive]);
            $enumAnswers = collect($validated['enum_answers'] ?? [])
                ->map(fn ($a) => trim((string) $a))
                ->filter(fn ($a) => $a !== '')
                ->values();

            foreach ($enumAnswers as $i => $answerText) {
                $item->choices()->create([
                    'choice_text' => $answerText,
                    'is_correct' => true,
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
