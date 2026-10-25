<?php

namespace App\Support;

use App\Models\Report;
use Illuminate\Support\Collection;

class ReportMetadata
{
    public const DEFAULT_CAMPUS = 'SAN CARLOS';

    public static function forAssessments(Collection $publishAssessments, int $studentCount, ?Report $report = null): array
    {
        $first = $publishAssessments->first();
        $assessment = $first?->assessment;
        $class = $first?->class;
        $instructorProfile = $class?->instructorProfile ?: $assessment?->instructorProfile;
        $department = $instructorProfile?->department;
        $college = $department?->college;
        $reportingTerm = (string) ($assessment?->reporting_term ?: 'General');
        $schoolYears = $publishAssessments
            ->pluck('class.school_year')
            ->filter()
            ->unique()
            ->values();

        return [
            'campus' => $report?->campus ?: self::DEFAULT_CAMPUS,
            'document_code' => $report?->document_code,
            'document_revision' => $report?->document_revision,
            'document_date' => $report?->document_date,
            'college' => $college?->shortName() ?? 'Not set',
            'department' => $department?->dept_name ?? 'Not set',
            'semester' => 'Second Semester',
            'school_year' => $schoolYears->count() === 1 ? $schoolYears->first() : 'Multiple school years',
            'reporting_term' => ucfirst($reportingTerm),
            'course_code_title' => ReportCourseTitle::forAssessments($publishAssessments),
            'students_count' => $studentCount,
            'note' => ($assessment?->report_category === Report::TYPE_SUMMATIVE)
                ? 'Note: Summative Assessments include the unit/chapter tests, midterm and final examination.'
                : 'Note: Graded Formative Assessments include the short quizzes, pre-class open-ended questions, end-in-class poll, concept map, homework completion, self-assessment, mind mapping, discussion, identifying misconceptions, exit slips, comprehension questions, doodle notes, quiz poll, think-pair-share, word journal.',
        ] + ($first ? ReportSignatures::forAssessment($first, $report) : [
            'prepared_by_name' => null,
            'reviewed_by_name' => null,
        ]);
    }
}
