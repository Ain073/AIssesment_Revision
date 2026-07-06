<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\ClassAssessment;
use App\Models\Department;
use App\Models\Report;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $reports = $this->finalizedReportsForDepartment($department)
            ->latest('updated_at')
            ->get();

        return view('department-chair.reports.index', $this->sharedData($user, 'reports') + [
            'department' => $department,
            'reports' => $reports,
            'formativeReports' => $reports->where('report_type', Report::TYPE_FORMATIVE)->values(),
            'summativeReports' => $reports->where('report_type', Report::TYPE_SUMMATIVE)->values(),
            'reportTypes' => $this->reportTypes(),
        ]);
    }

    public function show(ClassAssessment $classAssessment, string $type): View
    {
        abort_unless(array_key_exists($type, $this->reportTypes()), 404);

        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);

        abort_unless($department, 403, 'Department Chair account needs an assigned department.');

        $classAssessment->load([
            'assessment.items.choices',
            'assessment.instructorProfile.user',
            'assessment.instructorProfile.department.college',
            'assessment.subject',
            'class.students',
            'class.subject',
            'submissions.answers.choice',
        ]);

        abort_unless(
            $classAssessment->assessment?->instructorProfile?->department_id === $department->department_id,
            403,
            'This report is outside your department.'
        );

        $report = Report::query()
            ->where('class_assessment_id', $classAssessment->class_assessment_id)
            ->where('report_type', $type)
            ->where('report_status', Report::STATUS_FINALIZED)
            ->firstOrFail();

        return view('department-chair.reports.show', $this->sharedData($user, 'reports') + [
            'department' => $department,
            'report' => $report,
            'classAssessment' => $classAssessment,
            'assessment' => $classAssessment->assessment,
            'class' => $classAssessment->class,
            'analytics' => $this->reportAnalytics($classAssessment),
            'reportTypeLabel' => $this->reportTypes()[$type],
        ]);
    }

    private function finalizedReportsForDepartment(?Department $department): Builder
    {
        $query = Report::query()
            ->with([
                'classAssessment.assessment.subject',
                'classAssessment.assessment.instructorProfile.user',
                'classAssessment.assessment.instructorProfile.department',
                'classAssessment.class.subject',
            ])
            ->where('report_status', Report::STATUS_FINALIZED);

        if (! $department) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('classAssessment.assessment.instructorProfile', function ($profileQuery) use ($department): void {
            $profileQuery->where('department_id', $department->department_id);
        });
    }

    private function reportTypes(): array
    {
        return [
            Report::TYPE_FORMATIVE => 'Formative',
            Report::TYPE_SUMMATIVE => 'Summative',
        ];
    }

    private function reportAnalytics(ClassAssessment $classAssessment): array
    {
        $items = $classAssessment->assessment?->items ?? collect();
        $submissions = $classAssessment->submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->values();
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $studentScores = $submissions
            ->groupBy('student_profile_id')
            ->map(function (Collection $studentSubmissions) use ($items): float {
                return (float) $studentSubmissions
                    ->map(fn (Submission $submission): float => $this->submissionScore($submission, $items))
                    ->max();
            })
            ->values();
        $passingScore = $maxScore > 0 ? $maxScore * 0.75 : 0;

        return [
            'students_count' => $classAssessment->class?->students?->count() ?? 0,
            'takers_count' => $studentScores->count(),
            'item_count' => $items->count(),
            'highest_score' => $studentScores->isNotEmpty() ? $this->formatNumber((float) $studentScores->max()) : '0',
            'lowest_score' => $studentScores->isNotEmpty() ? $this->formatNumber((float) $studentScores->min()) : '0',
            'mean_score' => $studentScores->isNotEmpty() ? $this->formatNumber((float) $studentScores->avg()) : '0',
            'mean_percentage' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(((float) $studentScores->avg() / $maxScore) * 100, 2)
                : 0,
            'passing_rate' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(($studentScores->filter(fn (float $score): bool => $score >= $passingScore)->count() / $studentScores->count()) * 100, 2)
                : 0,
            'max_score' => $this->formatNumber($maxScore),
        ];
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function ($item) use ($answers): float {
            $answer = $answers->get($item->assessment_item_id);

            return $answer && $this->isCorrectAnswer($item, $answer)
                ? (float) $item->points
                : 0.0;
        });
    }

    private function isCorrectAnswer($item, $answer): bool
    {
        if ($answer->choice) {
            return (bool) $answer->choice->is_correct;
        }

        $correctAnswers = $item->choices
            ->where('is_correct', true)
            ->pluck('choice_text')
            ->map(fn ($choice): string => Str::lower(trim((string) $choice)))
            ->filter();
        $studentAnswer = Str::lower(trim((string) $answer->answer_text));

        return $studentAnswer !== '' && $correctAnswers->contains($studentAnswer);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
