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

        return view('instructor.reports', $this->sharedData($user, 'reports') + [
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
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $ownedCompletedAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['report_type'])
            ->values();

        if ($ownedCompletedAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
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
            'class_assessment_ids' => $classAssessmentIds->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'class_assessment_ids' => $classAssessmentIds->all(),
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
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $classAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['type'])
            ->values();

        if ($classAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $rows = $this->reportSheetRows($classAssessments, $validated['type']);

        return view('instructor.report-sheet', $this->sharedData($user, 'reports') + [
            'reportType' => $validated['type'],
            'reportTypeLabel' => $this->reportCategories()[$validated['type']],
            'classAssessmentIds' => $classAssessmentIds,
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
            'paper_size' => ['nullable', 'string', Rule::in(['a4', 'short', 'long'])],
            'course_code_title' => ['nullable', 'string', 'max:255'],
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
            'reports' => ['required', 'array'],
            'reports.*.concept_most_learned_skills' => ['nullable', 'string'],
            'reports.*.concept_least_learned_skills' => ['nullable', 'string'],
            'reports.*.issues_concern' => ['nullable', 'string'],
            'reports.*.interventions_done' => ['nullable', 'string'],
            'reports.*.future_plans_curriculum' => ['nullable', 'string'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $ownedCompletedAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['report_type'])
            ->values();

        if ($ownedCompletedAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $classAssessment) {
                $row = $validated['reports'][$classAssessment->class_assessment_id] ?? [];

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
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );
            }
        });

        Log::info('Instructor saved report details.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $classAssessmentIds->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'paper' => $validated['paper_size'] ?? 'long',
                'class_assessment_ids' => $classAssessmentIds->all(),
            ])
            ->with('status', 'Report details saved.');
    }

    public function generateAiDrafts(Request $request, ReportAiService $aiService): JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before generating AI drafts.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $ownedCompletedAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['report_type'])
            ->values();

        if ($ownedCompletedAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $drafts = $ownedCompletedAssessments
            ->mapWithKeys(function ($classAssessment) use ($aiService): array {
                return [
                    $classAssessment->class_assessment_id => $aiService->generate($classAssessment),
                ];
            });

        Log::info('Instructor generated AI report drafts.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $classAssessmentIds->all(),
            'source' => $drafts->pluck('source')->unique()->values()->all(),
        ]);

        return response()->json([
            'drafts' => $drafts,
        ]);
    }
}
