<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Report;
use App\Services\ReportAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends BaseController
{
    public function reports(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $completedAssessments = $this->completedReportableAssessments($instructorProfile);
        $formativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_FORMATIVE)->values();
        $summativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_SUMMATIVE)->values();

        return view('instructor.reports.index', $this->sharedData($user, 'reports') + [
            'instructorProfile' => $instructorProfile,
            'formativeAssessments' => $formativeAssessments,
            'summativeAssessments' => $summativeAssessments,
            'reportCategories' => $this->reportCategories(),
        ]);
    }

    public function prepareReports(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before preparing reports.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_keys' => ['required', 'array', 'min:1'],
            'class_assessment_keys.*' => ['required', 'uuid'],
        ]);

        $classAssessmentKeys = $this->selectedClassAssessmentKeys($validated['class_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $classAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $classAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'class_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $classAssessment) {
                Report::query()->firstOrCreate(
                    [
                        'class_assessment_id' => $classAssessment->class_assessment_id,
                        'report_type' => $validated['report_type'],
                    ],
                    [
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );
            }
        });

        Log::info('Instructor prepared report drafts.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $ownedCompletedAssessments->pluck('class_assessment_id')->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'class_assessment_keys' => $classAssessmentKeys->all(),
            ])
            ->with('status', 'Selected completed assessments are ready for report review.');
    }

    public function showReportSheet(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before viewing reports.');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_keys' => ['required', 'array', 'min:1'],
            'class_assessment_keys.*' => ['required', 'uuid'],
        ]);

        $classAssessmentKeys = $this->selectedClassAssessmentKeys($validated['class_assessment_keys']);
        $classAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $classAssessmentKeys,
            $validated['type'],
        );

        if ($classAssessments->count() !== $classAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'class_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $rows = $this->reportSheetRows($classAssessments, $validated['type']);

        return view('instructor.reports.sheet', $this->sharedData($user, 'reports') + [
            'reportType' => $validated['type'],
            'reportTypeLabel' => $this->reportCategories()[$validated['type']],
            'classAssessmentKeys' => $classAssessmentKeys,
            'rows' => $rows,
            'reportMeta' => $this->reportSheetMeta($classAssessments, $rows),
        ]);
    }

    public function saveReportSheet(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before saving reports.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'save_action' => ['required', 'string', Rule::in(['draft', 'finalized'])],
            'paper_size' => ['nullable', 'string', Rule::in(['a4', 'short', 'long'])],
            'course_code_title' => ['nullable', 'string', 'max:255'],
            'class_assessment_keys' => ['required', 'array', 'min:1'],
            'class_assessment_keys.*' => ['required', 'uuid'],
            'reports' => ['required', 'array'],
            'reports.*.concept_most_learned_skills' => ['nullable', 'string'],
            'reports.*.concept_least_learned_skills' => ['nullable', 'string'],
            'reports.*.issues_concern' => ['nullable', 'string'],
            'reports.*.interventions_done' => ['nullable', 'string'],
            'reports.*.future_plans_curriculum' => ['nullable', 'string'],
        ]);

        $classAssessmentKeys = $this->selectedClassAssessmentKeys($validated['class_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $classAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $classAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'class_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $classAssessment) {
                $row = $validated['reports'][$classAssessment->public_id] ?? [];

                Report::query()->updateOrCreate(
                    [
                        'class_assessment_id' => $classAssessment->class_assessment_id,
                        'report_type' => $validated['report_type'],
                    ],
                    [
                        'course_code_title' => $validated['course_code_title'] ?? null,
                        'concept_most_learned_skills' => $row['concept_most_learned_skills'] ?? null,
                        'concept_least_learned_skills' => $row['concept_least_learned_skills'] ?? null,
                        'issues_concern' => $row['issues_concern'] ?? null,
                        'interventions_done' => $validated['report_type'] === Report::TYPE_FORMATIVE
                            ? ($row['interventions_done'] ?? null)
                            : null,
                        'future_plans_curriculum' => $validated['report_type'] === Report::TYPE_SUMMATIVE
                            ? ($row['future_plans_curriculum'] ?? null)
                            : null,
                        'report_status' => $validated['save_action'] === 'finalized'
                            ? Report::STATUS_FINALIZED
                            : Report::STATUS_DRAFT,
                    ],
                );
            }
        });

        Log::info('Instructor saved report details.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $ownedCompletedAssessments->pluck('class_assessment_id')->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'paper' => $validated['paper_size'] ?? 'long',
                'class_assessment_keys' => $classAssessmentKeys->all(),
            ])
            ->with('status', $validated['save_action'] === 'finalized'
                ? 'Report finalized successfully.'
                : 'Report draft saved.');
    }

    public function generateAiDrafts(Request $request, ReportAiService $aiService): JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before generating AI drafts.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_keys' => ['required', 'array', 'min:1'],
            'class_assessment_keys.*' => ['required', 'uuid'],
        ]);

        $classAssessmentKeys = $this->selectedClassAssessmentKeys($validated['class_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $classAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $classAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'class_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        try {
            $drafts = $ownedCompletedAssessments
                ->mapWithKeys(function ($classAssessment) use ($aiService): array {
                    return [
                        $classAssessment->public_id => $aiService->generate($classAssessment),
                    ];
                });
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        Log::info('Instructor generated AI report drafts.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $ownedCompletedAssessments->pluck('class_assessment_id')->all(),
            'source' => $drafts->pluck('source')->unique()->values()->all(),
        ]);

        return response()->json([
            'drafts' => $drafts,
        ]);
    }

    private function selectedClassAssessmentKeys(array $keys)
    {
        return collect($keys)
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values();
    }

    private function selectedReportAssessments($instructorProfile, $classAssessmentKeys, string $reportType)
    {
        return $this->completedReportableAssessments($instructorProfile)
            ->whereIn('public_id', $classAssessmentKeys)
            ->where('assessment.report_category', $reportType)
            ->values();
    }
}
