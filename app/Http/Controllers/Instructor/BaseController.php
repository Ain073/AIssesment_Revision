<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSetting;
use App\Models\Assessment;
use App\Models\ClassAssessment;
use App\Models\ClassJoinRequest;
use App\Models\InstructorProfile;
use App\Models\Report;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\SubjectProgram;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BaseController extends Controller
{
    protected function instructorClassesData(User $user, string $activeClassTab = 'active'): array
    {
        $instructorProfile = $this->instructorProfile($user);
        $baseClassesQuery = $instructorProfile
            ? $instructorProfile->classes()
                ->with('subject')
                ->withCount('students')
            : null;
        $classes = $baseClassesQuery
            ? (clone $baseClassesQuery)
                ->when(
                    $activeClassTab === 'archived',
                    fn ($query) => $query->whereNotNull('archived_at'),
                    fn ($query) => $query->whereNull('archived_at')
                )
                ->latest($activeClassTab === 'archived' ? 'archived_at' : 'class_id')
                ->get()
            : collect();
        $activeClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNull('archived_at')->count()
            : 0;
        $archivedClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNotNull('archived_at')->count()
            : 0;
        $activeSubjectIds = $this->activeSubjectIds();
        $existingSubjectIds = $classes->pluck('subject_id')->filter();
        $activeSubjects = Subject::query()
            ->whereIn('subject_id', $activeSubjectIds)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
        $subjects = Subject::query()
            ->whereIn('subject_id', $activeSubjectIds->merge($existingSubjectIds)->unique())
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();

        return [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'subjects' => $subjects,
            'activeSubjects' => $activeSubjects,
            'activeClassTab' => $activeClassTab,
            'activeClassesCount' => $activeClassesCount,
            'archivedClassesCount' => $archivedClassesCount,
            'profileName' => $user->displayName(),
        ];
    }

    protected function classListTab(Request $request): string
    {
        return $request->query('tab') === 'archived' ? 'archived' : 'active';
    }

    protected function publishAssessmentToClasses(
        Request $request,
        User $user,
        ?InstructorProfile $instructorProfile,
        Assessment $ownedAssessment,
        array $validated
    ): void {
        $classIds = collect($validated['class_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $ownedClasses = $instructorProfile
            ? $instructorProfile->classes()
                ->where('subject_id', $ownedAssessment->subject_id)
                ->whereNull('archived_at')
                ->whereIn('class_id', $classIds)
                ->get()
            : collect();

        if ($ownedClasses->count() !== $classIds->count()) {
            throw ValidationException::withMessages([
                'class_ids' => 'You can only publish to your active classes under the same subject.',
            ]);
        }

        DB::transaction(function () use ($ownedAssessment, $ownedClasses, $validated, $request) {
            foreach ($ownedClasses as $class) {
                ClassAssessment::query()->updateOrCreate(
                    [
                        'assessment_id' => $ownedAssessment->assessment_id,
                        'class_id' => $class->class_id,
                    ],
                    [
                        'available_at' => $validated['available_at'] ?? null,
                        'due_at' => $validated['due_at'] ?? null,
                        'publish_status' => ClassAssessment::STATUS_PUBLISHED,
                        'score_visibility' => $request->boolean('score_visibility'),
                        'answer_visibility' => $request->boolean('answer_visibility'),
                        'prevent_copy_paste' => $request->boolean('prevent_copy_paste'),
                        'detect_tab_switch' => $request->boolean('detect_tab_switch'),
                        'screenshot_protection' => $request->boolean('screenshot_protection'),
                        'attempt_limit' => (int) $validated['attempt_limit'],
                        'shuffle_items' => $request->boolean('shuffle_items'),
                        'shuffle_choices' => $request->boolean('shuffle_choices'),
                        'warning_limit' => $validated['warning_limit'] ?? 3,
                        'display_mode' => $validated['display_mode'],
                    ],
                );
            }

            $ownedAssessment->update(['status' => Assessment::STATUS_READY]);
        });

        Log::info('Assessment published to classes by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'class_ids' => $ownedClasses->pluck('class_id')->all(),
        ]);
    }

    protected function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Instructor Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Instructor Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => $this->viewSwitches($user, 'instructor'),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    protected function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('instructor.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments')],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'summarize', 'href' => route('instructor.reports')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    protected function viewSwitches(User $user, string $activeMode): array
    {
        $switches = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Instructor',
                'icon' => 'co_present',
                'href' => route('instructor.dashboard'),
                'active' => $activeMode === 'instructor',
            ];
        }

        if ($user->hasRole('admin_dean')) {
            $switches[] = [
                'label' => 'Admin/Dean',
                'icon' => 'supervisor_account',
                'href' => route('admin-dean.dashboard'),
                'active' => $activeMode === 'admin_dean',
            ];
        }

        if ($user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Dept Chair',
                'icon' => 'assignment_ind',
                'href' => route('department-chair.dashboard'),
                'active' => $activeMode === 'department_chair',
            ];
        }

        return $switches;
    }

    protected function assessmentTypes(): array
    {
        return [
            'quiz' => 'Quiz',
            'exam' => 'Exam',
            'activity' => 'Activity',
            'assignment' => 'Assignment',
        ];
    }

    protected function itemTypes(): array
    {
        return [
            'multiple_choice' => 'Multiple Choice',
            'identification' => 'Identification',
            'essay' => 'Essay',
            'true_false' => 'True/False',
        ];
    }

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

    protected function reportSheetRows(Collection $classAssessments, string $reportType): Collection
    {
        return $classAssessments
            ->map(function (ClassAssessment $classAssessment) use ($reportType): array {
                $classAssessment->loadMissing([
                    'assessment.items.choices',
                    'assessment.subject',
                    'class.students',
                    'class.subject',
                    'report',
                    'submissions.answers.choice',
                ]);

                $analytics = $this->classAssessmentReportAnalytics($classAssessment);
                $report = Report::query()->firstOrCreate(
                    [
                        'class_assessment_id' => $classAssessment->class_assessment_id,
                        'report_type' => $reportType,
                    ],
                    [
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );

                return [
                    'classAssessment' => $classAssessment,
                    'assessment' => $classAssessment->assessment,
                    'class' => $classAssessment->class,
                    'analytics' => $analytics,
                    'report' => $report,
                ];
            })
            ->values();
    }

    protected function reportSheetMeta(Collection $classAssessments, Collection $rows): array
    {
        $first = $classAssessments->first();
        $assessment = $first?->assessment;
        $class = $first?->class;
        $subject = $assessment?->subject ?: $class?->subject;
        $instructorProfile = $class?->instructorProfile ?: $assessment?->instructorProfile;
        $department = $instructorProfile?->department;
        $college = $department?->college;
        $reportingTerm = (string) ($assessment?->reporting_term ?: 'General');
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
            'reporting_term' => ucfirst($reportingTerm),
            'course_code_title' => $savedCourseCodeTitle ?: $defaultCourseCodeTitle,
            'students_count' => $studentCount,
            'note' => ($assessment?->report_category === Report::TYPE_SUMMATIVE)
                ? 'Note: Summative Assessments include the unit/chapter tests, midterm and final examination.'
                : 'Note: Graded Formative Assessments include the short quizzes, pre-class open-ended questions, end-in-class poll, concept map, homework completion, self-assessment, mind mapping, discussion, identifying misconceptions, exit slips, comprehension questions, doodle notes, quiz poll, think-pair-share, word journal.',
        ];
    }

    protected function classAssessmentReportAnalytics(ClassAssessment $classAssessment): array
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
        $scoreableAssessments = $class->classAssessments
            ->filter(function (ClassAssessment $classAssessment): bool {
                $isCompleted = $classAssessment->publish_status === ClassAssessment::STATUS_CLOSED
                    || ($classAssessment->due_at && $classAssessment->due_at->isPast());
                $maxScore = (float) ($classAssessment->assessment?->items?->sum('points') ?? 0);

                return $isCompleted && $maxScore > 0;
            })
            ->values();

        return $class->students->mapWithKeys(function (StudentProfile $student) use ($scoreableAssessments): array {
            if ($scoreableAssessments->isEmpty()) {
                return [$student->student_profile_id => [
                    'has_results' => false,
                    'percentage' => null,
                    'passed' => null,
                ]];
            }

            $totalPercentage = $scoreableAssessments->sum(function (ClassAssessment $classAssessment) use ($student): float {
                $items = $classAssessment->assessment->items;
                $maxScore = (float) $items->sum('points');
                $bestScore = $classAssessment->submissions
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
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function ($item) use ($answers): float {
            $answer = $answers->get($item->assessment_item_id);

            return $answer && $this->isSubmissionAnswerCorrect($item, $answer)
                ? (float) $item->points
                : 0.0;
        });
    }

    protected function isSubmissionAnswerCorrect($item, $answer): bool
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

    protected function formatReportNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function completedReportableAssessments(?InstructorProfile $instructorProfile): Collection
    {
        if (! $instructorProfile) {
            return collect();
        }

        return ClassAssessment::query()
            ->with(['assessment.subject', 'class.subject', 'report'])
            ->withCount('submissions')
            ->whereHas('assessment', function ($query) use ($instructorProfile): void {
                $query->where('instructor_id', $instructorProfile->instructor_profile_id)
                    ->whereIn('report_category', array_keys($this->reportCategories()));
            })
            ->where(function ($query): void {
                $query->where('publish_status', ClassAssessment::STATUS_CLOSED)
                    ->orWhere(function ($dueQuery): void {
                        $dueQuery->whereNotNull('due_at')
                            ->where('due_at', '<=', now());
                    });
            })
            ->latest('due_at')
            ->latest('class_assessment_id')
            ->get();
    }

    protected function handledSubjects(?InstructorProfile $instructorProfile): Collection
    {
        if (! $instructorProfile) {
            return collect();
        }

        return Subject::query()
            ->whereIn('subject_id', $instructorProfile->classes()
                ->whereNotNull('subject_id')
                ->whereNull('archived_at')
                ->select('subject_id'))
            ->where('is_active', true)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
    }

    protected function activeSubjectIds(): Collection
    {
        $activeSemester = AcademicSetting::query()->value('active_semester');

        if (! $activeSemester) {
            return collect();
        }

        return SubjectProgram::query()
            ->where('semester', $activeSemester)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->distinct()
            ->pluck('subject_id');
    }

    protected function ownedAssessment(Assessment $assessment, ?InstructorProfile $instructorProfile): Assessment
    {
        abort_unless(
            $instructorProfile && $assessment->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to manage this assessment.'
        );

        return $assessment;
    }

    protected function ownedClassAssessment(ClassAssessment $classAssessment, ?InstructorProfile $instructorProfile): ClassAssessment
    {
        $classAssessment->loadMissing('assessment');

        abort_unless(
            $instructorProfile
                && $classAssessment->assessment
                && $classAssessment->assessment->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access these assessment results.'
        );

        return $classAssessment;
    }

    protected function classTabs(AcademicClass $class, string $activeTab): array
    {
        return [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'overview']),
                'active' => $activeTab === 'overview',
            ],
            [
                'key' => 'students',
                'label' => 'Students',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'students']),
                'active' => $activeTab === 'students',
            ],
            [
                'key' => 'assessments',
                'label' => 'Assessments',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'assessments']),
                'active' => $activeTab === 'assessments',
            ],
        ];
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    protected function instructorProfile(User $user): ?InstructorProfile
    {
        return $user->instructorProfile;
    }

    protected function ownedClass(AcademicClass $class, ?InstructorProfile $instructorProfile): AcademicClass
    {
        abort_unless(
            $instructorProfile && $class->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access this class.'
        );

        return $class;
    }

    protected function ensureActiveClass(AcademicClass $class): void
    {
        if (! $class->archived_at) {
            return;
        }

        throw ValidationException::withMessages([
            'class' => 'Archived classes are records only. Restore the class before making changes.',
        ]);
    }

    protected function ensureClassJoinAccess(AcademicClass $class): void
    {
        if ($class->join_token && $class->join_code) {
            return;
        }

        $class->forceFill(array_filter([
            'join_token' => $class->join_token ?: $this->generateClassJoinToken(),
            'join_code' => $class->join_code ?: $this->generateClassJoinCode(),
        ]))->save();
    }

    protected function generateClassJoinToken(): string
    {
        do {
            $token = Str::random(40);
        } while (AcademicClass::query()->where('join_token', $token)->exists());

        return $token;
    }

    protected function generateClassJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (AcademicClass::query()->where('join_code', $code)->exists());

        return $code;
    }

    protected function markJoinRequestApproved(AcademicClass $class, StudentProfile $studentProfile, int $responderId): void
    {
        ClassJoinRequest::query()->updateOrCreate(
            [
                'class_id' => $class->class_id,
                'student_profile_id' => $studentProfile->student_profile_id,
            ],
            [
                'status' => ClassJoinRequest::STATUS_APPROVED,
                'requested_at' => now(),
                'responded_at' => now(),
                'responded_by' => $responderId,
            ],
        );
    }

    protected function extractStudentNumbersFromRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $firstRow = array_map('trim', $rows[0]);
        $hasHeader = collect($firstRow)
            ->filter()
            ->map(fn (string $value) => Str::lower($value))
            ->contains('student_number');

        $studentNumbers = collect($hasHeader ? array_slice($rows, 1) : $rows)
            ->map(function (array $row) use ($firstRow, $hasHeader) {
                if ($hasHeader) {
                    $headerIndex = collect($firstRow)
                        ->search(fn ($value) => Str::lower((string) $value) === 'student_number');

                    return $headerIndex !== false ? trim((string) ($row[$headerIndex] ?? '')) : '';
                }

                return trim((string) ($row[0] ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $studentNumbers;
    }

    protected function buildImportPreview(AcademicClass $class, array $studentNumbers): array
    {
        $studentProfiles = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->whereIn('student_number', $studentNumbers)
            ->get()
            ->keyBy('student_number');

        $enrolledIds = $class->students()
            ->pluck('student_profiles.student_profile_id')
            ->all();

        $rows = [];
        $readyStudentProfileIds = [];
        $summary = [
            'total' => count($studentNumbers),
            'ready' => 0,
            'already_enrolled' => 0,
            'inactive' => 0,
            'not_found' => 0,
        ];

        foreach ($studentNumbers as $studentNumber) {
            $studentProfile = $studentProfiles->get($studentNumber);

            if (! $studentProfile || ! $studentProfile->user || ! $studentProfile->user->hasRole('student')) {
                $summary['not_found']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => 'No matching student account',
                    'program_name' => 'Unavailable',
                    'status_badge' => 'Not Found',
                    'status_class' => 'text-bg-secondary',
                ];

                continue;
            }

            if ($studentProfile->user->status !== 'active') {
                $summary['inactive']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => $studentProfile->user->displayName(),
                    'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                    'status_badge' => 'Inactive',
                    'status_class' => 'text-bg-warning',
                ];

                continue;
            }

            if (in_array($studentProfile->student_profile_id, $enrolledIds, true)) {
                $summary['already_enrolled']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => $studentProfile->user->displayName(),
                    'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                    'status_badge' => 'Already Enrolled',
                    'status_class' => 'text-bg-info',
                ];

                continue;
            }

            $summary['ready']++;
            $readyStudentProfileIds[] = $studentProfile->student_profile_id;
            $rows[] = [
                'student_number' => $studentNumber,
                'student_name' => $studentProfile->user->displayName(),
                'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                'status_badge' => 'Ready',
                'status_class' => 'text-bg-success',
            ];
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
            'ready_student_profile_ids' => array_values(array_unique($readyStudentProfileIds)),
        ];
    }

    protected function pullImportPreview(Request $request, AcademicClass $class): ?array
    {
        $importToken = $request->query('import_token');

        if (! is_string($importToken) || $importToken === '') {
            return null;
        }

        $storedImport = $request->session()->get("class_student_imports.{$importToken}");

        if (! $storedImport || ($storedImport['class_id'] ?? null) !== $class->class_id) {
            return null;
        }

        return [
            'token' => $importToken,
            'rows' => $storedImport['rows'] ?? [],
            'summary' => $storedImport['summary'] ?? [],
        ];
    }
}
