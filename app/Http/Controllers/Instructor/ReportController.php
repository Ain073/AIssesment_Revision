<?php

namespace App\Http\Controllers\Instructor;

use App\Models\Report;
use App\Services\ReportAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends BaseController
{
    public function reports(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $completedAssessments = $this->completedReportableAssessments($instructorProfile);
        $subjects = $completedAssessments
            ->map(fn ($publishAssessment) => $publishAssessment->assessment?->subject ?: $publishAssessment->class?->subject)
            ->filter(fn ($subject): bool => (bool) $subject?->subject_id)
            ->unique('subject_id')
            ->sortBy(fn ($subject): string => Str::lower(trim($subject->subject_code.' '.$subject->subject_name)))
            ->values();
        $classes = $completedAssessments
            ->map(fn ($publishAssessment) => $publishAssessment->class)
            ->filter(fn ($class): bool => (bool) $class?->class_id)
            ->unique('class_id')
            ->sortBy(fn ($class): string => Str::lower(trim($class->displayName().' '.$class->class_name)))
            ->values();
        $subjectId = $request->integer('subject');
        $selectedSubjectId = $subjects->contains('subject_id', $subjectId) ? $subjectId : null;
        $classId = $request->integer('class');
        $selectedClassId = $classes->contains('class_id', $classId) ? $classId : null;

        if ($selectedSubjectId) {
            $completedAssessments = $completedAssessments
                ->filter(function ($publishAssessment) use ($selectedSubjectId): bool {
                    $subject = $publishAssessment->assessment?->subject ?: $publishAssessment->class?->subject;

                    return (int) $subject?->subject_id === $selectedSubjectId;
                })
                ->values();
        }

        if ($selectedClassId) {
            $completedAssessments = $completedAssessments
                ->filter(fn ($publishAssessment): bool => (int) $publishAssessment->class?->class_id === $selectedClassId)
                ->values();
        }

        $formativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_FORMATIVE)->values();
        $summativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_SUMMATIVE)->values();

        return view('instructor.reports.index', $this->sharedData($user, 'reports') + [
            'instructorProfile' => $instructorProfile,
            'formativeAssessments' => $formativeAssessments,
            'summativeAssessments' => $summativeAssessments,
            'reportCategories' => $this->reportCategories(),
            'subjects' => $subjects,
            'classes' => $classes,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedClassId' => $selectedClassId,
        ]);
    }

    public function prepareReports(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before preparing reports.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'publish_assessment_keys' => ['required', 'array', 'min:1'],
            'publish_assessment_keys.*' => ['required', 'uuid'],
        ]);

        $publishAssessmentKeys = $this->selectedPublishAssessmentKeys($validated['publish_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $publishAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $publishAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'publish_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $publishAssessment) {
                Report::query()->firstOrCreate(
                    [
                        'publish_assessment_id' => $publishAssessment->publish_assessment_id,
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
            'publish_assessment_ids' => $ownedCompletedAssessments->pluck('publish_assessment_id')->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'publish_assessment_keys' => $publishAssessmentKeys->all(),
            ])
            ->with('status', 'Selected completed assessments are ready for report review.');
    }

    public function showReportSheet(Request $request, ReportAiService $aiService): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before viewing reports.');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'publish_assessment_keys' => ['required', 'array', 'min:1'],
            'publish_assessment_keys.*' => ['required', 'uuid'],
        ]);

        $publishAssessmentKeys = $this->selectedPublishAssessmentKeys($validated['publish_assessment_keys']);
        $publishAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $publishAssessmentKeys,
            $validated['type'],
        );

        if ($publishAssessments->count() !== $publishAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'publish_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $rows = $this->reportSheetRows($publishAssessments, $validated['type']);

        return view('instructor.reports.sheet', $this->sharedData($user, 'reports') + [
            'reportType' => $validated['type'],
            'reportTypeLabel' => $this->reportCategories()[$validated['type']],
            'publishAssessmentKeys' => $publishAssessmentKeys,
            'rows' => $rows,
            'reportMeta' => $this->reportSheetMeta($publishAssessments, $rows),
            'aiCandidates' => $aiService->candidateOptions(),
            'selectedAiProvider' => (string) config('services.ai_report.provider', 'openai'),
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
            'publish_assessment_keys' => ['required', 'array', 'min:1'],
            'publish_assessment_keys.*' => ['required', 'uuid'],
            'reports' => ['required', 'array'],
            'reports.*.concept_most_learned_skills' => ['nullable', 'string'],
            'reports.*.concept_least_learned_skills' => ['nullable', 'string'],
            'reports.*.issues_concern' => ['nullable', 'string'],
            'reports.*.interventions_done' => ['nullable', 'string'],
            'reports.*.future_plans_curriculum' => ['nullable', 'string'],
        ]);

        $publishAssessmentKeys = $this->selectedPublishAssessmentKeys($validated['publish_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $publishAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $publishAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'publish_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $firstAssessment = $ownedCompletedAssessments->first();
        $subject = $firstAssessment?->assessment?->subject ?: $firstAssessment?->class?->subject;
        $courseCodeTitle = $this->cleanReportCourseCodeTitle(
            $validated['course_code_title'] ?? null,
            $this->defaultCourseCodeTitle($subject, $firstAssessment?->class),
            $ownedCompletedAssessments,
        );

        DB::transaction(function () use ($ownedCompletedAssessments, $validated, $courseCodeTitle): void {
            foreach ($ownedCompletedAssessments as $publishAssessment) {
                $row = $validated['reports'][$publishAssessment->public_id] ?? [];

                Report::query()->updateOrCreate(
                    [
                        'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                        'report_type' => $validated['report_type'],
                    ],
                    [
                        'course_code_title' => $courseCodeTitle,
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
            'publish_assessment_ids' => $ownedCompletedAssessments->pluck('publish_assessment_id')->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'paper' => $validated['paper_size'] ?? 'long',
                'publish_assessment_keys' => $publishAssessmentKeys->all(),
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
            'publish_assessment_keys' => ['required', 'array', 'min:1'],
            'publish_assessment_keys.*' => ['required', 'uuid'],
            'ai_provider' => ['required', 'string', Rule::in(['openai', 'claude'])],
        ]);

        $publishAssessmentKeys = $this->selectedPublishAssessmentKeys($validated['publish_assessment_keys']);
        $ownedCompletedAssessments = $this->selectedReportAssessments(
            $instructorProfile,
            $publishAssessmentKeys,
            $validated['report_type'],
        );

        if ($ownedCompletedAssessments->count() !== $publishAssessmentKeys->count()) {
            throw ValidationException::withMessages([
                'publish_assessment_keys' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        try {
            $drafts = $ownedCompletedAssessments
                ->mapWithKeys(function ($publishAssessment) use ($aiService, $validated): array {
                    return [
                        $publishAssessment->public_id => $aiService->generate($publishAssessment, $validated['ai_provider']),
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
            'publish_assessment_ids' => $ownedCompletedAssessments->pluck('publish_assessment_id')->all(),
            'ai_provider' => $validated['ai_provider'],
            'source' => $drafts->pluck('source')->unique()->values()->all(),
        ]);

        return response()->json([
            'drafts' => $drafts,
        ]);
    }

    private function selectedPublishAssessmentKeys(array $keys)
    {
        return collect($keys)
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values();
    }

    private function selectedReportAssessments($instructorProfile, $publishAssessmentKeys, string $reportType)
    {
        return $this->completedReportableAssessments($instructorProfile)
            ->whereIn('public_id', $publishAssessmentKeys)
            ->where('assessment.report_category', $reportType)
            ->values();
    }
}
