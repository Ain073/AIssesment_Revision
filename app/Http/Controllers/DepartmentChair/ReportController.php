<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\ClassAssessment;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Report;
use App\Models\Submission;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends BaseController
{
    public function index(Request $request): View
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $teachers = $this->reportTeachersForDepartment($department);
        $subjects = $this->reportSubjectsForDepartment($department);
        $teacherId = $request->integer('teacher');
        $subjectId = $request->integer('subject');
        $selectedTeacherId = $teachers->contains('instructor_profile_id', $teacherId) ? $teacherId : null;
        $selectedSubjectId = $subjects->contains('subject_id', $subjectId) ? $subjectId : null;

        $reports = $this->finalizedReportsForDepartment($department)
            ->when($selectedTeacherId, function (Builder $query, int $instructorProfileId): void {
                $query->whereHas('classAssessment.assessment', function (Builder $assessmentQuery) use ($instructorProfileId): void {
                    $assessmentQuery->where('instructor_id', $instructorProfileId);
                });
            })
            ->when($selectedSubjectId, function (Builder $query, int $subjectId): void {
                $query->whereHas('classAssessment.assessment', function (Builder $assessmentQuery) use ($subjectId): void {
                    $assessmentQuery->where('subject_id', $subjectId);
                });
            })
            ->latest('updated_at')
            ->get();

        return view('department-chair.reports.index', $this->sharedData($user, 'reports') + [
            'department' => $department,
            'reports' => $reports,
            'formativeReports' => $reports->where('report_type', Report::TYPE_FORMATIVE)->values(),
            'summativeReports' => $reports->where('report_type', Report::TYPE_SUMMATIVE)->values(),
            'reportTypes' => $this->reportTypes(),
            'teachers' => $teachers,
            'subjects' => $subjects,
            'selectedTeacherId' => $selectedTeacherId,
            'selectedSubjectId' => $selectedSubjectId,
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
            'class.instructorProfile.department.college',
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
        $rows = collect([
            [
                'classAssessment' => $classAssessment,
                'assessment' => $classAssessment->assessment,
                'class' => $classAssessment->class,
                'analytics' => $this->reportAnalytics($classAssessment),
                'report' => $report,
            ],
        ]);

        return view('department-chair.reports.show', $this->sharedData($user, 'reports') + [
            'department' => $department,
            'report' => $report,
            'classAssessment' => $classAssessment,
            'assessment' => $classAssessment->assessment,
            'class' => $classAssessment->class,
            'analytics' => $rows->first()['analytics'],
            'reportTypeLabel' => $this->reportTypes()[$type],
            'reportType' => $type,
            'rows' => $rows,
            'reportMeta' => $this->reportSheetMeta(collect([$classAssessment]), $rows),
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

    private function reportTeachersForDepartment(?Department $department): Collection
    {
        if (! $department) {
            return collect();
        }

        return InstructorProfile::query()
            ->with('user')
            ->where('department_id', $department->department_id)
            ->whereHas('assessments.classAssessments.reports', function (Builder $query): void {
                $query->where('report_status', Report::STATUS_FINALIZED);
            })
            ->get()
            ->sortBy(fn (InstructorProfile $profile): string => Str::lower($profile->user?->displayName() ?? ''))
            ->values();
    }

    private function reportSubjectsForDepartment(?Department $department): Collection
    {
        if (! $department) {
            return collect();
        }

        return Subject::query()
            ->whereHas('assessments', function (Builder $assessmentQuery) use ($department): void {
                $assessmentQuery
                    ->whereHas('instructorProfile', function (Builder $profileQuery) use ($department): void {
                        $profileQuery->where('department_id', $department->department_id);
                    })
                    ->whereHas('classAssessments.reports', function (Builder $reportQuery): void {
                        $reportQuery->where('report_status', Report::STATUS_FINALIZED);
                    });
            })
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
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

    private function reportSheetMeta(Collection $classAssessments, Collection $rows): array
    {
        $first = $classAssessments->first();
        $assessment = $first?->assessment;
        $class = $first?->class;
        $subject = $assessment?->subject ?: $class?->subject;
        $instructorProfile = $class?->instructorProfile ?: $assessment?->instructorProfile;
        $department = $instructorProfile?->department;
        $college = $department?->college;
        $schoolYears = $classAssessments
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
            'course_code_title' => $savedCourseCodeTitle ?: $defaultCourseCodeTitle,
            'students_count' => $studentCount,
            'note' => ($assessment?->report_category === Report::TYPE_SUMMATIVE)
                ? 'Note: Summative Assessments include the unit/chapter tests, midterm and final examination.'
                : 'Note: Graded Formative Assessments include the short quizzes, pre-class open-ended questions, end-in-class poll, concept map, homework completion, self-assessment, mind mapping, discussion, identifying misconceptions, exit slips, comprehension questions, doodle notes, quiz poll, think-pair-share, word journal.',
        ];
    }
}
