<?php

namespace App\Http\Controllers\Instructor;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassDetail;
use App\Models\PublishAssessment;
use App\Models\Subject;
use App\Models\Submission;
use App\Services\AuditLogger;
use App\Support\RecordSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentController extends BaseController
{
    public function assessments(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $handledSubjects = $this->handledSubjects($instructorProfile);
        $activeAssessmentTab = $request->query('tab') === 'published' ? 'published' : 'draft';
        $draftSubjectOptions = $instructorProfile
            ? Subject::whereIn('subject_id', $instructorProfile->assessments()
                ->where('status', '!=', Assessment::STATUS_ARCHIVED)->select('subject_id'))
                ->orderBy('subject_code')->get()
            : collect();
        $publishedClassOptions = $instructorProfile
            ? AcademicClass::whereIn('class_id', ClassDetail::whereIn('class_details_id', PublishAssessment::query()
                ->whereHas('assessment', fn ($query) => $query->where('instructor_id', $instructorProfile->instructor_profile_id))
                ->select('class_details_id'))->select('class_id'))
                ->with('program')->get()->sortBy(fn ($class) => $class->class_name)->values()
            : collect();
        $assessments = $instructorProfile
            ? $instructorProfile->assessments()
                ->with(['subject', 'items.choices', 'publishAssessments.classDetail.class'])
                ->where('status', '!=', Assessment::STATUS_ARCHIVED)
                ->tap(fn ($query) => RecordSearch::apply($query, ['title', 'subject.subject_code', 'subject.subject_name']))
                ->withCount([
                    'items',
                    'publishAssessments',
                ])
                ->latest('assessment_id')
                ->get()
            : collect();
        $classesBySubject = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
                ->whereNull('classes.archived_at')
                ->orderBy('classes.year_level')
                ->orderBy('classes.section_name')
                ->get()
                ->groupBy('subject_id')
            : collect();
        $publishedAssessments = $instructorProfile
            ? PublishAssessment::query()
                ->with(['assessment.subject', 'classDetail.class.subject'])
                ->withCount(['submissions as submitted_count' => fn ($query) => $query->where('status', Submission::STATUS_SUBMITTED)])
                ->whereHas('assessment', fn ($query) => $query->where('instructor_id', $instructorProfile->instructor_profile_id))
                ->tap(fn ($query) => RecordSearch::apply($query, RecordSearch::PUBLISHED_FIELDS))
                ->latest('publish_assessment_id')
                ->get()
                ->each(function (PublishAssessment $publishAssessment) {
                    $publishAssessment->display_status = $publishAssessment->publish_status === PublishAssessment::STATUS_CLOSED
                        || ($publishAssessment->due_at && $publishAssessment->due_at->isPast())
                            ? 'completed'
                            : 'pending';
                })
            : collect();

        return view('instructor.assessments.index', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $handledSubjects,
            'assessments' => $assessments,
            'publishedAssessments' => $publishedAssessments,
            'draftSubjectOptions' => $draftSubjectOptions,
            'publishedClassOptions' => $publishedClassOptions,
            'activeAssessmentTab' => $activeAssessmentTab,
            'classesBySubject' => $classesBySubject,
            'assessmentTypes' => $this->assessmentTypes(),
            'itemTypes' => $this->itemTypes(),
        ]);
    }

    public function createAssessment(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        return view('instructor.assessments.create', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $this->handledSubjects($instructorProfile),
            'assessmentTypes' => $this->assessmentTypes(),
            'reportCategories' => $this->reportCategories(),
            'reportingTerms' => $this->reportingTerms(),
        ]);
    }

    public function showAssessment(Assessment $assessment): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);
        $isPublishedSnapshot = $ownedAssessment->status === Assessment::STATUS_ARCHIVED
            || $ownedAssessment->publishAssessments()->exists();

        $ownedAssessment->load([
            'subject',
            'items.choices',
            'publishAssessments.classDetail.class',
        ])->loadCount('items', 'publishAssessments');

        $publishableClasses = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
                ->whereHas('contextDetail', fn ($query) => $query->where('subject_id', $ownedAssessment->subject_id))
                ->whereNull('classes.archived_at')
                ->orderBy('classes.year_level')
                ->orderBy('classes.section_name')
                ->get()
            : collect();

        return view('instructor.assessments.show', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedAssessment,
            'publishableClasses' => $publishableClasses,
            'isPublishedSnapshot' => $isPublishedSnapshot,
            'hasStudentSubmissions' => $ownedAssessment->publishAssessments()
                ->whereHas('submissions')
                ->exists(),
            'assessmentTypes' => $this->assessmentTypes(),
            'itemTypes' => $this->itemTypes(),
            'reportCategories' => $this->reportCategories(),
            'reportingTerms' => $this->reportingTerms(),
        ]);
    }

    public function storeAssessment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating assessments.');

        $handledSubjectIds = $this->handledSubjects($instructorProfile)->pluck('subject_id')->all();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($handledSubjectIds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(array_keys($this->assessmentTypes()))],
            'report_category' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'reporting_term' => ['required', 'string', Rule::in(array_keys($this->reportingTerms()))],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $assessment = $instructorProfile->assessments()->create($validated + [
            'status' => Assessment::STATUS_DRAFT,
        ]);

        Log::info('Assessment created by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessment->assessment_id,
            'subject_id' => $assessment->subject_id,
        ]);

        AuditLogger::log('CREATE', 'Assessments', "Created assessment '{$assessment->title}'", $assessment);

        return redirect()
            ->route('instructor.assessments.show', $assessment)
            ->with('status', 'Assessment saved as draft. You can now add items.');
    }

    public function updateAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);
        $this->ensureAssessmentIsEditable($ownedAssessment, 'Published assessment records cannot be updated.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(array_keys($this->assessmentTypes()))],
            'report_category' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'reporting_term' => ['required', 'string', Rule::in(array_keys($this->reportingTerms()))],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $ownedAssessment->update($validated);

        Log::info('Assessment details updated by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
        ]);

        AuditLogger::log('UPDATE', 'Assessments', "Updated assessment '{$ownedAssessment->title}'", $ownedAssessment);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Assessment details updated.');
    }

    public function destroyAssessment(Request $request, Assessment $assessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $hasClassAssignments = $ownedAssessment->publishAssessments()->exists();

        $assessmentId = $ownedAssessment->assessment_id;
        $assessmentTitle = $ownedAssessment->title;

        if ($hasClassAssignments) {
            $ownedAssessment->update(['status' => Assessment::STATUS_ARCHIVED]);

            Log::info('Assessment archived by instructor.', [
                'actor_id' => $user->id,
                'assessment_id' => $assessmentId,
                'assessment_title' => $assessmentTitle,
                'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
            ]);

            AuditLogger::log('ARCHIVE', 'Assessments', "Archived assessment '{$assessmentTitle}' (has published copies)");

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Assessment removed from Draft / Stored. Published records remain available.']);
            }

            return redirect()
                ->route('instructor.assessments', ['tab' => 'draft'])
                ->with('status', 'Assessment removed from Draft / Stored. Published records remain available.');
        }

        DB::transaction(function () use ($ownedAssessment) {
            $ownedAssessment->items()->delete();
            $ownedAssessment->delete();
        });

        Log::warning('Assessment deleted by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessmentId,
            'assessment_title' => $assessmentTitle,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        AuditLogger::log('DELETE', 'Assessments', "Deleted assessment '{$assessmentTitle}'");

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Assessment deleted successfully.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'draft'])
            ->with('status', 'Assessment deleted successfully.');
    }
}
