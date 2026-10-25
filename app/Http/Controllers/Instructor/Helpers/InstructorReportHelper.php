<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicClass;
use App\Models\InstructorProfile;
use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use App\Support\ReportAnalytics;
use App\Support\ReportCourseTitle;
use App\Support\ReportItemAnalysis;
use App\Support\ReportMetadata;
use Illuminate\Support\Collection;

trait InstructorReportHelper
{
    protected function reportCategories(): array
    {
        return [
            'formative' => 'Formative',
            'summative' => 'Summative',
        ];
    }

    protected function reportingTerms(): array
    {
        return [
            'midterm' => 'Midterm',
            'final' => 'Final',
        ];
    }

    protected function reportSheetRows(Collection $publishAssessments, string $reportType): Collection
    {
        return $publishAssessments
            ->map(function (PublishAssessment $publishAssessment) use ($reportType): array {
                $publishAssessment->loadMissing([
                    'assessment.items.choices',
                    'assessment.subject',
                    'classDetail.class.students',
                    'classDetail.class.subject',
                    'report',
                    'submissions.answers.choice',
                ]);

                $analytics = $this->publishAssessmentReportAnalytics($publishAssessment);
                $report = Report::query()->firstOrCreate(
                    [
                        'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                        'report_type' => $reportType,
                    ],
                    [
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );

                return [
                    'publishAssessment' => $publishAssessment,
                    'assessment' => $publishAssessment->assessment,
                    'class' => $publishAssessment->class,
                    'analytics' => $analytics,
                    'report' => $report,
                ];
            })
            ->values();
    }

    protected function reportSheetMeta(Collection $publishAssessments, Collection $rows): array
    {
        $distinctClasses = $publishAssessments
            ->map(fn (PublishAssessment $assessment) => $assessment->class)
            ->filter()
            ->unique(fn ($class) => $class->getKey());
        $studentCount = $distinctClasses->isNotEmpty()
            ? (int) $distinctClasses->sum(fn ($class) => $class->enrolledStudentsCount() ?? 0)
            : (int) ($rows->first()['analytics']['students_count'] ?? 0);

        return ReportMetadata::forAssessments($publishAssessments, $studentCount, $rows->first()['report'] ?? null);
    }

    protected function defaultCourseCodeTitle(Collection $publishAssessments): string
    {
        return ReportCourseTitle::forAssessments($publishAssessments);
    }

    protected function publishAssessmentReportAnalytics(PublishAssessment $publishAssessment): array
    {
        $analytics = ReportAnalytics::summarize($publishAssessment);
        $items = $publishAssessment->assessment?->items ?? collect();
        $itemAnalysis = collect(ReportItemAnalysis::summarize($items, $publishAssessment->submissions));

        return $analytics + [
            'item_analysis' => $itemAnalysis->all(),
            'strongest_items' => ReportItemAnalysis::strongestItems($itemAnalysis),
            'weakest_items' => ReportItemAnalysis::weakestItems($itemAnalysis),
        ];
    }

    protected function classPerformanceSummary(AcademicClass $class): array
    {
        $studentPerformance = $this->studentPerformanceByStudent($class)
            ->where('has_results', true);
        $studentCount = $studentPerformance->count();
        $passedCount = $studentPerformance->where('passed', true)->count();
        $failedCount = $studentCount - $passedCount;
        $hasResults = $studentCount > 0;

        return [
            'class' => $class,
            'has_results' => $hasResults,
            'passed_percentage' => $hasResults ? round(($passedCount / $studentCount) * 100, 1) : 0,
            'failed_percentage' => $hasResults ? round(($failedCount / $studentCount) * 100, 1) : 0,
        ];
    }

    protected function studentPerformanceByStudent(AcademicClass $class): Collection
    {
        $scoreableAssessments = $class->publishAssessments
            ->filter(function (PublishAssessment $publishAssessment): bool {
                $isCompleted = $publishAssessment->publish_status === PublishAssessment::STATUS_CLOSED
                    || ($publishAssessment->due_at && $publishAssessment->due_at->isPast());
                $maxScore = (float) ($publishAssessment->assessment?->items?->sum('points') ?? 0);

                return $isCompleted && $maxScore > 0;
            })
            ->values();

        return $class->enrolledStudentsCollection(['user'])->mapWithKeys(function (StudentProfile $student) use ($scoreableAssessments, $class): array {
            if ($scoreableAssessments->isEmpty()) {
                return [$student->student_profile_id => [
                    'has_results' => false,
                    'percentage' => null,
                    'passed' => null,
                ]];
            }

            $totalPercentage = $scoreableAssessments->sum(function (PublishAssessment $publishAssessment) use ($student): float {
                $items = $publishAssessment->assessment?->items ?? collect();
                $maxScore = (float) $items->sum('points');

                if ($maxScore <= 0) {
                    return 0.0;
                }

                $bestScore = $this->reportableSubmissions($publishAssessment->submissions ?? collect())
                    ->where('student_profile_id', $student->student_profile_id)
                    ->map(fn (Submission $submission): float => $this->submissionScore($submission, $items))
                    ->max() ?? 0;

                return ($bestScore / $maxScore) * 100;
            });
            $percentage = round($totalPercentage / max($scoreableAssessments->count(), 1), 1);

            return [$student->student_profile_id => [
                'has_results' => true,
                'percentage' => $percentage,
                'passed' => $percentage >= PassingRateSetting::rateForYearLevel($class->year_level),
            ]];
        });
    }

    protected function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
    }

    protected function reportableSubmissions(Collection $submissions): Collection
    {
        return ReportAnalytics::reportableSubmissions($submissions);
    }

    protected function isSubmissionAnswerCorrect($item, $answer): bool
    {
        return AssessmentScoring::isCorrect($item, $answer);
    }

    protected function formatReportNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function completedReportableAssessments(?InstructorProfile $instructorProfile): Collection
    {
        if (! $instructorProfile) {
            return collect();
        }

        return PublishAssessment::query()
            ->with(['assessment.subject', 'classDetail.class.subject', 'report'])
            ->withCount('submissions')
            ->whereHas('assessment', function ($query) use ($instructorProfile): void {
                $query->where('instructor_id', $instructorProfile->instructor_profile_id)
                    ->whereIn('report_category', array_keys($this->reportCategories()));
            })
            ->where(function ($query): void {
                $query->where('publish_status', PublishAssessment::STATUS_CLOSED)
                    ->orWhere(function ($dueQuery): void {
                        $dueQuery->whereNotNull('due_at')
                            ->where('due_at', '<=', now());
                    });
            })
            ->orderBy('assessment_id')
            ->orderBy('due_at')
            ->orderBy('publish_assessment_id')
            ->get();
    }
}
