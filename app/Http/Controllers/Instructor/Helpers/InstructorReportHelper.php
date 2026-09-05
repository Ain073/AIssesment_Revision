<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicClass;
use App\Models\PublishAssessment;
use App\Models\InstructorProfile;
use App\Models\Report;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
                    'class.students',
                    'class.subject',
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
        $first = $publishAssessments->first();
        $assessment = $first?->assessment;
        $class = $first?->class;
        $subject = $assessment?->subject ?: $class?->subject;
        $instructorProfile = $class?->instructorProfile ?: $assessment?->instructorProfile;
        $department = $instructorProfile?->department;
        $college = $department?->college;
        $reportingTerm = (string) ($assessment?->reporting_term ?: 'General');
        $schoolYears = $publishAssessments
            ->pluck('class.school_year')
            ->filter()
            ->unique()
            ->values();
        $studentCount = (int) ($rows->first()['analytics']['students_count'] ?? 0);
        $defaultCourseCodeTitle = trim(($subject?->subject_code ?? 'No code').' / '.($subject?->subject_name ?? 'No subject'), ' /');
        $savedCourseCodeTitle = $rows
            ->pluck('report.course_code_title')
            ->filter()
            ->first();

        return [
            'campus' => 'SAN CARLOS',
            'college' => $college?->college_name ?? 'Not set',
            'department' => $department?->dept_name ?? 'Not set',
            'semester' => 'Second Semester',
            'school_year' => $schoolYears->count() === 1 ? $schoolYears->first() : 'Multiple school years',
            'reporting_term' => ucfirst($reportingTerm),
            'course_code_title' => $savedCourseCodeTitle ?: $defaultCourseCodeTitle,
            'students_count' => $studentCount,
            'note' => ($assessment?->report_category === Report::TYPE_SUMMATIVE)
                ? 'Note: Summative Assessments include the unit/chapter tests, midterm and final examination.'
                : 'Note: Graded Formative Assessments include the short quizzes, pre-class open-ended questions, end-in-class poll, concept map, homework completion, self-assessment, mind mapping, discussion, identifying misconceptions, exit slips, comprehension questions, doodle notes, quiz poll, think-pair-share, word journal.',
        ];
    }

    protected function publishAssessmentReportAnalytics(PublishAssessment $publishAssessment): array
    {
        $items = $publishAssessment->assessment?->items ?? collect();
        $submissions = $publishAssessment->submissions
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
            'students_count' => $publishAssessment->class?->enrolledStudentsCount() ?? 0,
            'takers_count' => $studentScores->count(),
            'item_count' => $items->count(),
            'highest_score' => $studentScores->isNotEmpty() ? $this->formatReportNumber((float) $studentScores->max()) : '0',
            'lowest_score' => $studentScores->isNotEmpty() ? $this->formatReportNumber((float) $studentScores->min()) : '0',
            'mean_score' => $studentScores->isNotEmpty() ? $this->formatReportNumber((float) $studentScores->avg()) : '0',
            'mean_percentage' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(((float) $studentScores->avg() / $maxScore) * 100, 2)
                : 0,
            'passing_rate' => $studentScores->isNotEmpty() && $maxScore > 0
                ? round(($studentScores->filter(fn (float $score): bool => $score >= $passingScore)->count() / $studentScores->count()) * 100, 2)
                : 0,
            'max_score' => $this->formatReportNumber($maxScore),
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

        return $class->enrolledStudentsCollection(['user'])->mapWithKeys(function (StudentProfile $student) use ($scoreableAssessments): array {
            if ($scoreableAssessments->isEmpty()) {
                return [$student->student_profile_id => [
                    'has_results' => false,
                    'percentage' => null,
                    'passed' => null,
                ]];
            }

            $totalPercentage = $scoreableAssessments->sum(function (PublishAssessment $publishAssessment) use ($student): float {
                $items = $publishAssessment->assessment->items;
                $maxScore = (float) $items->sum('points');
                $bestScore = $publishAssessment->submissions
                    ->where('student_profile_id', $student->student_profile_id)
                    ->map(fn (Submission $submission): float => $this->submissionScore($submission, $items))
                    ->max() ?? 0;

                return ($bestScore / $maxScore) * 100;
            });
            $percentage = round($totalPercentage / $scoreableAssessments->count(), 1);

            return [$student->student_profile_id => [
                'has_results' => true,
                'percentage' => $percentage,
                'passed' => $percentage >= 75,
            ]];
        });
    }

    protected function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
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
            ->with(['assessment.subject', 'class.subject', 'report'])
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
