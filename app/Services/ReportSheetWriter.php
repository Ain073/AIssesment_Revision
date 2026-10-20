<?php

namespace App\Services;

use App\Models\Report;
use App\Support\ReportCourseTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportSheetWriter
{
    /** The controller supplies assessments selected through its existing ownership/completion scope. */
    public function save(Collection $assessments, array $validated, bool $autosave = false): array
    {
        return DB::transaction(function () use ($assessments, $validated, $autosave): array {
            $reports = Report::query()
                ->whereIn('publish_assessment_id', $assessments->pluck('publish_assessment_id'))
                ->where('report_type', $validated['report_type'])
                ->orderBy('report_id')
                ->lockForUpdate()
                ->get()->keyBy('publish_assessment_id');

            // Validate the entire sheet before writing any row, including mixed finalized/draft sheets.
            foreach ($assessments as $assessment) {
                $key = $assessment->public_id;
                $report = $reports->get($assessment->publish_assessment_id);
                if (! $report || ! array_key_exists($key, $validated['reports'])
                    || ! array_key_exists($key, $validated['revisions'])) {
                    throw ValidationException::withMessages(['reports' => 'Reload the report sheet before saving.']);
                }
                if (($autosave && $report->report_status === Report::STATUS_FINALIZED)
                    || (int) $validated['revisions'][$key] !== (int) $report->revision) {
                    throw ValidationException::withMessages([
                        'report_conflict' => 'This report changed in another tab or was finalized. Copy your unsaved text, then reload before saving again.',
                    ]);
                }
            }

            $revisions = [];
            foreach ($assessments as $assessment) {
                $key = $assessment->public_id;
                $report = $reports->get($assessment->publish_assessment_id);
                $row = $validated['reports'][$key];
                $report->fill([
                    'course_code_title' => ReportCourseTitle::forAssessment($assessment),
                    'concept_most_learned_skills' => $row['concept_most_learned_skills'] ?? null,
                    'concept_least_learned_skills' => $row['concept_least_learned_skills'] ?? null,
                    'issues_concern' => $row['issues_concern'] ?? null,
                    'interventions_done' => $validated['report_type'] === Report::TYPE_FORMATIVE ? ($row['interventions_done'] ?? null) : null,
                    'future_plans_curriculum' => $validated['report_type'] === Report::TYPE_SUMMATIVE ? ($row['future_plans_curriculum'] ?? null) : null,
                    'report_status' => $autosave ? Report::STATUS_DRAFT : $validated['save_action'],
                ]);
                if ($report->isDirty()) {
                    $report->revision = (int) $report->revision + 1;
                    $report->save();
                }
                $revisions[$key] = (int) $report->revision;
            }

            return $revisions;
        }, 3);
    }
}
