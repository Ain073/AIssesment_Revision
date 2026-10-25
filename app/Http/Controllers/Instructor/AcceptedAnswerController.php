<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\PublishAssessment;
use App\Services\AcceptedAnswerCorrection;
use App\Services\AuditLogger;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class AcceptedAnswerController extends BaseController
{
    public function edit(Assessment $assessment, AssessmentItem $item): View
    {
        $this->authorizeCorrection($assessment, $item);

        return $this->correctionView($assessment, $item);
    }

    public function preview(Request $request, Assessment $assessment, AssessmentItem $item, AcceptedAnswerCorrection $correction): View
    {
        $this->authorizeCorrection($assessment, $item);
        $validated = $request->validate(['alternative_answer' => ['required', 'string', 'max:1000']]);
        $preview = $correction->preview($item, $validated['alternative_answer']);
        $token = Crypt::encryptString(json_encode([
            'actor_id' => $this->currentUser()->getKey(),
            'assessment_id' => $assessment->getKey(),
            'item_id' => $item->getKey(),
            'alternative' => $preview['alternative'],
            'key_revision' => $preview['key_revision'],
            'impact_revision' => $preview['impact_revision'],
            'expires_at' => now()->addMinutes(20)->timestamp,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $this->correctionView($assessment, $item, $preview, $token);
    }

    public function store(Request $request, Assessment $assessment, AssessmentItem $item, AcceptedAnswerCorrection $correction): RedirectResponse
    {
        $this->authorizeCorrection($assessment, $item);
        $validated = $request->validate(['preview_token' => ['required', 'string', 'max:12000']]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['preview_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['preview_token' => 'The preview is invalid. Preview the alternative again.']);
        }

        if (! is_array($payload)
            || ($payload['actor_id'] ?? null) !== $this->currentUser()->getKey()
            || ($payload['assessment_id'] ?? null) !== $assessment->getKey()
            || ($payload['item_id'] ?? null) !== $item->getKey()
            || ($payload['expires_at'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['preview_token' => 'The preview has expired or belongs to another question. Preview the alternative again.']);
        }

        $preview = DB::transaction(function () use ($assessment, $item, $payload, $correction): array {
            $lockedAssessment = Assessment::query()->whereKey($assessment->getKey())->lockForUpdate()->firstOrFail();
            $lockedItem = AssessmentItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeCorrection($lockedAssessment, $lockedItem);
            $preview = $correction->preview($lockedItem, $payload['alternative']);

            if ($preview['key_revision'] !== $payload['key_revision'] || $preview['impact_revision'] !== $payload['impact_revision']) {
                throw ValidationException::withMessages(['preview_token' => 'The accepted answers or affected submissions changed. Preview the alternative again.']);
            }

            $lockedItem->choices()->create([
                'choice_text' => $preview['alternative'],
                'is_correct' => true,
                'sort_order' => ((int) $lockedItem->choices->max('sort_order')) + 1,
            ]);

            AuditLogger::logOrFail('ADD_ACCEPTED_ANSWER', 'Grading',
                "Added an accepted alternative for question #{$lockedItem->sort_order} in published assessment '{$lockedAssessment->title}'; {$preview['students_count']} student(s), {$preview['attempts_count']} submitted attempt(s) gained credit.",
                $lockedItem, [
                    'assessment_id' => $lockedAssessment->getKey(),
                    'publish_assessment_ids' => $lockedAssessment->publishAssessments()->pluck('publish_assessment_id')->all(),
                    'accepted_answers_before' => $preview['accepted'],
                    'accepted_answers_after' => [...$preview['accepted'], $preview['alternative']],
                    'affected_students_count' => $preview['students_count'],
                    'affected_attempts_count' => $preview['attempts_count'],
                ]);

            return $preview;
        });

        return redirect()->route('instructor.assessments.items.accepted-answers.edit', [$assessment, $item])
            ->with('status', "Alternative saved. Scores now include this answer for all students using this published question ({$preview['attempts_count']} existing submitted attempt(s) gained credit). Reload results to see the updated scores.");
    }

    private function authorizeCorrection(Assessment $assessment, AssessmentItem $item): void
    {
        $this->ownedAssessment($assessment, $this->instructorProfile($this->currentUser()));
        abort_unless($item->assessment_id === $assessment->getKey(), 404);
        abort_unless($item->item_type === 'identification' && $assessment->publishAssessments()
            ->whereIn('publish_status', [PublishAssessment::STATUS_PUBLISHED, PublishAssessment::STATUS_CLOSED])
            ->exists(), 404);
        $item->load('choices');
    }

    private function correctionView(Assessment $assessment, AssessmentItem $item, ?array $preview = null, ?string $token = null): View
    {
        return view('instructor.assessments.accepted-answers', $this->sharedData($this->currentUser(), 'assessments') + [
            'assessment' => $assessment,
            'item' => $item,
            'preview' => $preview,
            'previewToken' => $token,
            'publications' => $assessment->publishAssessments()->with('classDetail.class')->get(),
        ]);
    }
}
