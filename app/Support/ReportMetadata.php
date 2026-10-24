<?php

namespace App\Support;

use App\Models\Report;
use Illuminate\Support\Collection;

class ReportMetadata
{
    public static function forAssessments(Collection $publishAssessments, int $studentCount): array
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
            'campus' => 'SAN CARLOS',
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
        ];
    }
}
