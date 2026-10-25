<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Subject;
use App\Support\RecordSearch;
use App\Support\ReportAnalytics;
use App\Support\ReportMetadata;
use App\Support\ReportPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
                $query->whereHas('publishAssessment.assessment', function (Builder $assessmentQuery) use ($instructorProfileId): void {
                    $assessmentQuery->where('instructor_id', $instructorProfileId);
                });
            })
            ->when($selectedSubjectId, function (Builder $query, int $subjectId): void {
                $query->whereHas('publishAssessment.assessment', function (Builder $assessmentQuery) use ($subjectId): void {
                    $assessmentQuery->where('subject_id', $subjectId);
                });
            })
            ->tap(fn ($query) => RecordSearch::apply($query, ['publishAssessment.assessment.title', 'publishAssessment.assessment.subject.subject_code', 'publishAssessment.assessment.subject.subject_name', 'publishAssessment.classDetail.class.section_name']))
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

    public function show(PublishAssessment $publishAssessment, string $type): View|RedirectResponse
    {
        if (! array_key_exists($type, $this->reportTypes())) {
            return redirect()
                ->route('department-chair.reports')
                ->withErrors(['report' => 'The selected report type is not available.']);
        }

        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);

        abort_unless($department, 403, 'Department Chair account needs an assigned department.');

        $publishAssessment->load([
            'assessment.items.choices',
            'assessment.instructorProfile.user',
            'assessment.instructorProfile.department.college',
            'assessment.subject',
            'classDetail.class.instructorProfile.department.college',
            'classDetail.class.students',
            'classDetail.class.subject',
            'submissions.answers.choice',
        ]);

        abort_unless(
            $publishAssessment->assessment?->instructorProfile?->department_id === $department->department_id,
            403,
            'This report is outside your department.'
        );

        $report = Report::query()
            ->where('publish_assessment_id', $publishAssessment->publish_assessment_id)
            ->where('report_type', $type)
            ->where('report_status', Report::STATUS_FINALIZED)
            ->first();

        if (! $report) {
            return redirect()
                ->route('department-chair.reports')
                ->withErrors(['report' => 'No finalized report is available for that assessment yet.']);
        }

        $rows = collect([
            [
                'publishAssessment' => $publishAssessment,
                'assessment' => $publishAssessment->assessment,
                'class' => $publishAssessment->class,
                'analytics' => $this->reportAnalytics($publishAssessment),
                'report' => $report,
            ],
        ]);

        return view('department-chair.reports.show', $this->sharedData($user, 'reports') + [
            'department' => $department,
            'report' => $report,
            'publishAssessment' => $publishAssessment,
            'assessment' => $publishAssessment->assessment,
            'class' => $publishAssessment->class,
            'analytics' => $rows->first()['analytics'],
            'reportTypeLabel' => $this->reportTypes()[$type],
            'reportType' => $type,
            'rows' => $rows,
            'reportMeta' => $this->reportSheetMeta(collect([$publishAssessment]), $rows),
        ]);
    }

    public function showPdf(Request $request, PublishAssessment $publishAssessment, string $type): Response|RedirectResponse
    {
        $view = $this->show($publishAssessment, $type);
        if ($view instanceof RedirectResponse) {
            return $view;
        }
        $paper = $request->query('paper');

        return ReportPdf::stream($view->getData(), is_string($paper) ? $paper : 'long');
    }

    private function finalizedReportsForDepartment(?Department $department): Builder
    {
        $query = Report::query()
            ->with([
                'publishAssessment.assessment.subject',
                'publishAssessment.assessment.instructorProfile.user',
                'publishAssessment.assessment.instructorProfile.department',
                'publishAssessment.classDetail.class.subject',
            ])
            ->where('report_status', Report::STATUS_FINALIZED);

        if (! $department) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('publishAssessment.assessment.instructorProfile', function ($profileQuery) use ($department): void {
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
            ->whereHas('assessments.publishAssessments.reports', function (Builder $query): void {
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
                    ->whereHas('publishAssessments.reports', function (Builder $reportQuery): void {
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

    private function reportAnalytics(PublishAssessment $publishAssessment): array
    {
        return ReportAnalytics::summarize($publishAssessment);
    }

    private function reportSheetMeta(Collection $publishAssessments, Collection $rows): array
    {
        return ReportMetadata::forAssessments(
            $publishAssessments,
            (int) ($rows->first()['analytics']['students_count'] ?? 0),
            $rows->first()['report'] ?? null,
        );
    }
}
