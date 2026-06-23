<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassAssessment;
use App\Models\ClassJoinRequest;
use App\Models\InstructorProfile;
use App\Models\Report;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use App\Services\TabularFileReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->whereNull('archived_at')
                ->with([
                    'subject',
                    'students',
                    'classAssessments' => fn ($query) => $query
                        ->where(function ($statusQuery): void {
                            $statusQuery->where('publish_status', ClassAssessment::STATUS_CLOSED)
                                ->orWhere(function ($dueQuery): void {
                                    $dueQuery->whereNotNull('due_at')
                                        ->where('due_at', '<=', now());
                                });
                        })
                        ->with([
                            'assessment.items.choices',
                            'submissions' => fn ($submissionQuery) => $submissionQuery
                                ->where('status', Submission::STATUS_SUBMITTED)
                                ->with('answers.choice'),
                        ]),
                ])
                ->withCount('students')
                ->latest('class_id')
                ->get()
            : collect();
        $classIds = $classes->pluck('class_id');
        $classesCount = $classes->count();
        $studentsCount = $classIds->isNotEmpty()
            ? DB::table('class_students')
                ->whereIn('class_id', $classIds)
                ->distinct('student_profile_id')
                ->count('student_profile_id')
            : 0;
        $assessmentsCount = $instructorProfile?->assessments()->count() ?? 0;

        return view('instructor.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Classes Handled',
                    'value' => $classesCount,
                    'caption' => 'Classes currently linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Students',
                    'value' => $studentsCount,
                    'caption' => 'Across your active classes',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Assessments',
                    'value' => $assessmentsCount,
                    'caption' => 'Reusable assessments you created',
                    'icon' => 'assignment',
                ],
            ],
            'activeClassPerformance' => $classes
                ->map(fn (AcademicClass $class): array => $this->classPerformanceSummary($class)),
        ]);
    }

    public function classes(Request $request): View
    {
        $user = $this->currentUser();

        return view('instructor.classes', $this->sharedData($user, 'classes') + $this->instructorClassesData($user, $this->classListTab($request)));
    }

    public function classesLive(Request $request): View
    {
        return view('instructor.partials.classes-live', $this->instructorClassesData($this->currentUser(), $this->classListTab($request)));
    }

    private function instructorClassesData(User $user, string $activeClassTab = 'active'): array
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
        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();

        return [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'subjects' => $subjects,
            'activeClassTab' => $activeClassTab,
            'activeClassesCount' => $activeClassesCount,
            'archivedClassesCount' => $archivedClassesCount,
            'profileName' => $user->displayName(),
        ];
    }

    private function classListTab(Request $request): string
    {
        return $request->query('tab') === 'archived' ? 'archived' : 'active';
    }

    public function storeClass(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating classes.');

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,subject_id'],
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $class = $instructorProfile->classes()->create($validated + [
            'join_token' => $this->generateClassJoinToken(),
            'join_code' => $this->generateClassJoinCode(),
        ]);

        Log::info('Class created by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $class->class_id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'subject_id' => $class->subject_id,
            'class_name' => $class->class_name,
            'school_year' => $class->school_year,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class added successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class added successfully.');
    }

    public function updateClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,subject_id'],
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $ownedClass->update($validated);

        Log::info('Class updated by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
            'subject_id' => $ownedClass->subject_id,
            'class_name' => $ownedClass->class_name,
            'school_year' => $ownedClass->school_year,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class updated successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class updated successfully.');
    }

    public function destroyClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);
        $classId = $ownedClass->class_id;
        $className = $ownedClass->class_name;

        DB::transaction(function () use ($ownedClass) {
            $ownedClass->students()->detach();
            $ownedClass->joinRequests()->delete();
            $ownedClass->classAssessments()->delete();
            $ownedClass->delete();
        });

        Log::warning('Class deleted by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $classId,
            'class_name' => $className,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class deleted successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class deleted successfully.');
    }

    public function archiveClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $ownedClass->update(['archived_at' => now()]);

        Log::info('Class archived by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_name' => $ownedClass->class_name,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class archived successfully.']);
        }

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class archived successfully.');
    }

    public function restoreClass(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $ownedClass->update(['archived_at' => null]);

        Log::info('Class restored by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_name' => $ownedClass->class_name,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Class restored successfully.']);
        }

        return redirect()
            ->route('instructor.classes', ['tab' => 'archived'])
            ->with('status', 'Class restored successfully.');
    }

    public function showClass(Request $request, AcademicClass $class): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);
        $activeTab = in_array($request->query('tab'), ['overview', 'students', 'assessments'], true)
            ? $request->query('tab')
            : 'students';

        $ownedClass->load([
            'subject',
            'instructorProfile.department.college',
            'students.user.roles',
            'students.program.college',
            'classAssessments' => fn ($query) => $query
                ->with([
                    'assessment.subject',
                    'assessment.items.choices',
                    'submissions' => fn ($submissionQuery) => $submissionQuery
                        ->where('status', Submission::STATUS_SUBMITTED)
                        ->with('answers.choice'),
                ])
                ->latest('class_assessment_id'),
            'joinRequests' => fn ($query) => $query
                ->where('status', ClassJoinRequest::STATUS_PENDING)
                ->with(['studentProfile.user.roles', 'studentProfile.program.college'])
                ->latest('requested_at'),
        ])->loadCount('students', 'classAssessments');

        $ownedClass->classAssessments->each(function (ClassAssessment $classAssessment): void {
            $classAssessment->display_status = $classAssessment->publish_status === ClassAssessment::STATUS_CLOSED
                || ($classAssessment->due_at && $classAssessment->due_at->isPast())
                    ? 'completed'
                    : 'pending';
        });

        $this->ensureClassJoinAccess($ownedClass);

        return view('instructor.class-show', $this->sharedData($user, 'classes') + [
            'class' => $ownedClass,
            'activeTab' => $activeTab,
            'classTabs' => $this->classTabs($ownedClass, $activeTab),
            'classJoinLink' => route('student.classes.join.show', $ownedClass->join_token),
            'pendingJoinRequests' => $ownedClass->joinRequests
                ->sortByDesc('requested_at')
                ->values(),
            'enrolledStudents' => $ownedClass->students
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values(),
            'classAssessments' => $ownedClass->classAssessments,
            'studentPerformance' => $this->studentPerformanceByStudent($ownedClass),
            'importPreview' => $this->pullImportPreview($request, $ownedClass),
        ]);
    }

    public function storeClassStudent(Request $request, AcademicClass $class): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:255'],
        ]);

        $studentNumber = trim($validated['student_number']);

        $studentProfile = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->where('student_number', $studentNumber)
            ->first();

        if (! $studentProfile || ! $studentProfile->user || ! $studentProfile->user->hasRole('student')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No student account was found for that student number.',
                    'errors' => [
                        'student_number' => ['No student account was found for that student number.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'No student account was found for that student number.'])
                ->withInput();
        }

        if ($studentProfile->user->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This student account is inactive and cannot be added to a class yet.',
                    'errors' => [
                        'student_number' => ['This student account is inactive and cannot be added to a class yet.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'This student account is inactive and cannot be added to a class yet.'])
                ->withInput();
        }

        if ($ownedClass->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This student is already enrolled in the selected class.',
                    'errors' => [
                        'student_number' => ['This student is already enrolled in the selected class.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'This student is already enrolled in the selected class.'])
                ->withInput();
        }

        $ownedClass->students()->attach($studentProfile->student_profile_id);
        $this->markJoinRequestApproved($ownedClass, $studentProfile, $user->id);

        Log::info('Student enrolled into class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'student_number' => $studentProfile->student_number,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Student added to class successfully.']);
        }

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student added to class successfully.');
    }

    public function destroyClassStudent(Request $request, AcademicClass $class, StudentProfile $studentProfile): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        if (! $ownedClass->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'That student is not currently enrolled in this class.',
                    'errors' => [
                        'student_file' => ['That student is not currently enrolled in this class.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_file' => 'That student is not currently enrolled in this class.']);
        }

        $ownedClass->students()->detach($studentProfile->student_profile_id);

        Log::warning('Student removed from class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'student_number' => $studentProfile->student_number,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Student removed from class successfully.']);
        }

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student removed from class successfully.');
    }

    public function previewClassStudentsImport(Request $request, AcademicClass $class, TabularFileReader $fileReader): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $validated = $request->validate([
            'student_file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,txt,xlsx',
            ],
        ]);

        $numbers = $this->extractStudentNumbersFromRows($fileReader->read(
            $validated['student_file']->getRealPath(),
            Str::lower((string) $validated['student_file']->getClientOriginalExtension())
        ));

        if (empty($numbers)) {
            throw ValidationException::withMessages([
                'student_file' => 'No student numbers were found in the uploaded file.',
            ]);
        }

        if (count($numbers) > 200) {
            throw ValidationException::withMessages([
                'student_file' => 'Please limit each import to 200 student numbers or fewer.',
            ]);
        }

        $importPreview = $this->buildImportPreview($ownedClass, $numbers);
        $importToken = (string) Str::uuid();

        $request->session()->put("class_student_imports.{$importToken}", [
            'class_id' => $ownedClass->class_id,
            'student_profile_ids' => $importPreview['ready_student_profile_ids'],
            'rows' => $importPreview['rows'],
            'summary' => $importPreview['summary'],
        ]);

        return redirect()
            ->route('instructor.classes.show', [
                'class' => $ownedClass,
                'tab' => 'students',
                'import_token' => $importToken,
            ])
            ->with('status', 'File processed. Review the preview before confirming the import.');
    }

    public function confirmClassStudentsImport(Request $request, AcademicClass $class): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $sessionKey = "class_student_imports.{$validated['import_token']}";
        $storedImport = $request->session()->get($sessionKey);

        if (! $storedImport || ($storedImport['class_id'] ?? null) !== $ownedClass->class_id) {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_file' => 'The import preview has expired. Please upload the file again.']);
        }

        $studentProfileIds = collect($storedImport['student_profile_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $alreadyEnrolledIds = $ownedClass->students()
            ->whereIn('student_profiles.student_profile_id', $studentProfileIds)
            ->pluck('student_profiles.student_profile_id');

        $attachIds = $studentProfileIds
            ->diff($alreadyEnrolledIds)
            ->values();

        if ($attachIds->isNotEmpty()) {
            $ownedClass->students()->attach($attachIds->all());
            ClassJoinRequest::query()
                ->where('class_id', $ownedClass->class_id)
                ->whereIn('student_profile_id', $attachIds->all())
                ->update([
                    'status' => ClassJoinRequest::STATUS_APPROVED,
                    'responded_at' => now(),
                    'responded_by' => $user->id,
                    'updated_at' => now(),
                ]);
        }

        $request->session()->forget($sessionKey);

        Log::info('Students imported into class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'imported_count' => $attachIds->count(),
            'student_profile_ids' => $attachIds->all(),
        ]);

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', $attachIds->count().' student'.($attachIds->count() === 1 ? '' : 's').' imported successfully.');
    }

    public function downloadClassStudentsImportSample(AcademicClass $class): Response
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        $fileName = Str::slug($ownedClass->class_name ?: 'class').'-student-import-sample.csv';
        $content = implode("\n", [
            'student_number',
            '2024-00001',
            '2024-00002',
            '2024-00003',
        ])."\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function approveClassJoinRequest(Request $request, AcademicClass $class, ClassJoinRequest $joinRequest): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        abort_unless($joinRequest->class_id === $ownedClass->class_id, 404);

        $joinRequest->load('studentProfile.user.roles');
        $studentProfile = $joinRequest->studentProfile;

        if (! $studentProfile || ! $studentProfile->user || ! $studentProfile->user->hasRole('student')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The requesting student account no longer exists.',
                    'errors' => [
                        'join_request' => ['The requesting student account no longer exists.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['join_request' => 'The requesting student account no longer exists.']);
        }

        if ($studentProfile->user->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This student account is inactive and cannot be approved yet.',
                    'errors' => [
                        'join_request' => ['This student account is inactive and cannot be approved yet.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['join_request' => 'This student account is inactive and cannot be approved yet.']);
        }

        if (! $ownedClass->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            $ownedClass->students()->attach($studentProfile->student_profile_id);
        }

        $this->markJoinRequestApproved($ownedClass, $studentProfile, $user->id);

        Log::info('Class join request approved by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_join_request_id' => $joinRequest->class_join_request_id,
            'student_profile_id' => $studentProfile->student_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Student join request approved.']);
        }

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student join request approved.');
    }

    public function rejectClassJoinRequest(Request $request, AcademicClass $class, ClassJoinRequest $joinRequest): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $this->ensureActiveClass($ownedClass);

        abort_unless($joinRequest->class_id === $ownedClass->class_id, 404);

        if ($joinRequest->status !== ClassJoinRequest::STATUS_PENDING) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Only pending join requests can be rejected.',
                    'errors' => [
                        'join_request' => ['Only pending join requests can be rejected.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['join_request' => 'Only pending join requests can be rejected.']);
        }

        $joinRequest->update([
            'status' => ClassJoinRequest::STATUS_REJECTED,
            'responded_at' => now(),
            'responded_by' => $user->id,
        ]);

        Log::info('Class join request rejected by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_join_request_id' => $joinRequest->class_join_request_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Student join request rejected.']);
        }

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student join request rejected.');
    }

    public function assessments(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $handledSubjects = $this->handledSubjects($instructorProfile);
        $activeAssessmentTab = $request->query('tab') === 'published' ? 'published' : 'draft';
        $assessments = $instructorProfile
            ? $instructorProfile->assessments()
                ->with(['subject', 'items.choices', 'classAssessments.class'])
                ->withCount('items', 'classAssessments')
                ->latest('assessment_id')
                ->get()
            : collect();
        $classesBySubject = $instructorProfile
            ? $instructorProfile->classes()
                ->with('subject')
                ->whereNull('archived_at')
                ->orderBy('class_name')
                ->get()
                ->groupBy('subject_id')
            : collect();
        $publishedAssessments = $instructorProfile
            ? ClassAssessment::query()
                ->with(['assessment.subject', 'class.subject'])
                ->withCount(['submissions as submitted_count' => fn ($query) => $query->where('status', Submission::STATUS_SUBMITTED)])
                ->whereHas('assessment', fn ($query) => $query->where('instructor_id', $instructorProfile->instructor_profile_id))
                ->latest('class_assessment_id')
                ->get()
                ->each(function (ClassAssessment $classAssessment) {
                    $classAssessment->display_status = $classAssessment->publish_status === ClassAssessment::STATUS_CLOSED
                        || ($classAssessment->due_at && $classAssessment->due_at->isPast())
                            ? 'completed'
                            : 'pending';
                })
            : collect();

        return view('instructor.assessments', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $handledSubjects,
            'assessments' => $assessments,
            'publishedAssessments' => $publishedAssessments,
            'activeAssessmentTab' => $activeAssessmentTab,
            'classesBySubject' => $classesBySubject,
            'assessmentTypes' => $this->assessmentTypes(),
            'itemTypes' => $this->itemTypes(),
        ]);
    }

    public function assessmentResults(ClassAssessment $classAssessment): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClassAssessment = $this->ownedClassAssessment($classAssessment, $instructorProfile);
        $isCompleted = $ownedClassAssessment->publish_status === ClassAssessment::STATUS_CLOSED
            || ($ownedClassAssessment->due_at && $ownedClassAssessment->due_at->isPast());

        abort_unless($isCompleted, 404, 'Results are available after the assessment is completed.');

        $ownedClassAssessment->load([
            'assessment.subject',
            'assessment.items.choices',
            'class.subject',
            'class.students.user',
            'submissions' => fn ($query) => $query
                ->with(['studentProfile.user', 'answers.choice', 'securityEvents'])
                ->orderBy('attempt_number'),
        ]);

        $items = $ownedClassAssessment->assessment?->items ?? collect();
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $passingScore = $maxScore * 0.75;
        $submissions = $ownedClassAssessment->submissions;
        $students = $ownedClassAssessment->class?->students ?? collect();
        $submissionStudents = $submissions
            ->pluck('studentProfile')
            ->filter();

        $studentResults = $students
            ->concat($submissionStudents)
            ->unique('student_profile_id')
            ->sortBy(fn (StudentProfile $student): string => Str::lower($student->user?->displayName() ?? $student->student_number ?? ''))
            ->map(function (StudentProfile $student) use ($submissions, $items, $maxScore, $passingScore): array {
                $attempts = $submissions
                    ->where('student_profile_id', $student->student_profile_id)
                    ->map(function (Submission $submission) use ($items, $maxScore, $passingScore): array {
                        $isSubmitted = $submission->status === Submission::STATUS_SUBMITTED;
                        $score = $isSubmitted ? $this->submissionScore($submission, $items) : null;

                        return [
                            'status' => $submission->status,
                            'score' => $score !== null ? $this->formatReportNumber($score) : null,
                            'score_value' => $score,
                            'passed' => $score !== null && $maxScore > 0 && $score >= $passingScore,
                            'submitted_at' => $submission->submitted_at,
                            'warning_count' => $submission->warning_count,
                            'completion_reason' => $submission->completion_reason,
                            'security_events' => $submission->securityEvents
                                ->map(fn ($event): array => [
                                    'label' => $event->displayLabel(),
                                    'occurred_at' => $event->occurred_at,
                                ])
                                ->values(),
                        ];
                    })
                    ->values();
                $bestAttempt = $attempts
                    ->where('status', Submission::STATUS_SUBMITTED)
                    ->sortByDesc('score_value')
                    ->first();
                $submittedAttemptsCount = $attempts
                    ->where('status', Submission::STATUS_SUBMITTED)
                    ->count();

                return [
                    'student' => $student,
                    'attempts' => $attempts,
                    'best_attempt' => $bestAttempt,
                    'submitted_attempts_count' => $submittedAttemptsCount,
                ];
            })
            ->values();
        $analytics = $this->classAssessmentReportAnalytics($ownedClassAssessment);
        $autoSubmittedCount = $submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->where('completion_reason', Submission::COMPLETION_WARNING_LIMIT)
            ->count();

        Log::info('Instructor viewed completed assessment results.', [
            'actor_id' => $user->id,
            'class_assessment_id' => $ownedClassAssessment->class_assessment_id,
            'assessment_id' => $ownedClassAssessment->assessment_id,
            'class_id' => $ownedClassAssessment->class_id,
        ]);

        return view('instructor.assessment-results', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedClassAssessment->assessment,
            'class' => $ownedClassAssessment->class,
            'studentResults' => $studentResults,
            'analytics' => $analytics,
            'autoSubmittedCount' => $autoSubmittedCount,
        ]);
    }

    public function reports(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $completedAssessments = $this->completedReportableAssessments($instructorProfile);
        $formativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_FORMATIVE)->values();
        $summativeAssessments = $completedAssessments->where('assessment.report_category', Report::TYPE_SUMMATIVE)->values();
        $draftReportsCount = $instructorProfile
            ? Report::query()
                ->where('report_status', Report::STATUS_DRAFT)
                ->whereHas('classAssessment.assessment', fn ($query) => $query->where('instructor_id', $instructorProfile->instructor_profile_id))
                ->count()
            : 0;

        return view('instructor.reports', $this->sharedData($user, 'reports') + [
            'instructorProfile' => $instructorProfile,
            'completedAssessments' => $completedAssessments,
            'formativeAssessments' => $formativeAssessments,
            'summativeAssessments' => $summativeAssessments,
            'draftReportsCount' => $draftReportsCount,
            'reportCategories' => $this->reportCategories(),
        ]);
    }

    public function prepareReports(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before preparing reports.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $ownedCompletedAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['report_type'])
            ->values();

        if ($ownedCompletedAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $classAssessment) {
                Report::query()->firstOrCreate(
                    [
                        'class_assessment_id' => $classAssessment->class_assessment_id,
                        'report_type' => $validated['report_type'],
                    ],
                    [
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );
            }
        });

        Log::info('Instructor prepared report drafts.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $classAssessmentIds->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'class_assessment_ids' => $classAssessmentIds->all(),
            ])
            ->with('status', 'Selected completed assessments are ready for report review.');
    }

    public function showReportSheet(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before viewing reports.');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $classAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['type'])
            ->values();

        if ($classAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        $rows = $this->reportSheetRows($classAssessments, $validated['type']);

        return view('instructor.report-sheet', $this->sharedData($user, 'reports') + [
            'reportType' => $validated['type'],
            'reportTypeLabel' => $this->reportCategories()[$validated['type']],
            'classAssessmentIds' => $classAssessmentIds,
            'rows' => $rows,
            'reportMeta' => $this->reportSheetMeta($classAssessments, $rows),
        ]);
    }

    public function saveReportSheet(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before saving reports.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(array_keys($this->reportCategories()))],
            'paper_size' => ['nullable', 'string', Rule::in(['a4', 'short', 'long'])],
            'course_code_title' => ['nullable', 'string', 'max:255'],
            'class_assessment_ids' => ['required', 'array', 'min:1'],
            'class_assessment_ids.*' => ['integer'],
            'reports' => ['required', 'array'],
            'reports.*.concept_most_learned_skills' => ['nullable', 'string'],
            'reports.*.concept_least_learned_skills' => ['nullable', 'string'],
            'reports.*.issues_concern' => ['nullable', 'string'],
            'reports.*.interventions_done' => ['nullable', 'string'],
            'reports.*.future_plans_curriculum' => ['nullable', 'string'],
        ]);

        $classAssessmentIds = collect($validated['class_assessment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
        $ownedCompletedAssessments = $this->completedReportableAssessments($instructorProfile)
            ->whereIn('class_assessment_id', $classAssessmentIds)
            ->where('assessment.report_category', $validated['report_type'])
            ->values();

        if ($ownedCompletedAssessments->count() !== $classAssessmentIds->count()) {
            throw ValidationException::withMessages([
                'class_assessment_ids' => 'Select completed assessments under your account with the same report type.',
            ]);
        }

        DB::transaction(function () use ($ownedCompletedAssessments, $validated): void {
            foreach ($ownedCompletedAssessments as $classAssessment) {
                $row = $validated['reports'][$classAssessment->class_assessment_id] ?? [];

                Report::query()->updateOrCreate(
                    [
                        'class_assessment_id' => $classAssessment->class_assessment_id,
                        'report_type' => $validated['report_type'],
                    ],
                    [
                        'course_code_title' => $validated['course_code_title'] ?? null,
                        'concept_most_learned_skills' => $row['concept_most_learned_skills'] ?? null,
                        'concept_least_learned_skills' => $row['concept_least_learned_skills'] ?? null,
                        'issues_concern' => $row['issues_concern'] ?? null,
                        'interventions_done' => $validated['report_type'] === Report::TYPE_FORMATIVE
                            ? ($row['interventions_done'] ?? null)
                            : null,
                        'future_plans_curriculum' => $validated['report_type'] === Report::TYPE_SUMMATIVE
                            ? ($row['future_plans_curriculum'] ?? null)
                            : null,
                        'report_status' => Report::STATUS_DRAFT,
                    ],
                );
            }
        });

        Log::info('Instructor saved report details.', [
            'actor_id' => $user->id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'report_type' => $validated['report_type'],
            'class_assessment_ids' => $classAssessmentIds->all(),
        ]);

        return redirect()
            ->route('instructor.reports.build', [
                'type' => $validated['report_type'],
                'paper' => $validated['paper_size'] ?? 'long',
                'class_assessment_ids' => $classAssessmentIds->all(),
            ])
            ->with('status', 'Report details saved.');
    }

    public function createAssessment(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        return view('instructor.assessment-create', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $this->handledSubjects($instructorProfile),
            'assessmentTypes' => $this->assessmentTypes(),
            'reportCategories' => $this->reportCategories(),
            'reportingTerms' => $this->reportingTerms(),
        ]);
    }

    public function publishAssessmentForm(Request $request): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $handledSubjects = $this->handledSubjects($instructorProfile);
        $assessments = $instructorProfile
            ? $instructorProfile->assessments()
                ->with('subject')
                ->withCount('items')
                ->orderBy('title')
                ->get()
            : collect();
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->with('subject')
                ->whereNull('archived_at')
                ->orderBy('class_name')
                ->get()
            : collect();

        $selectedAssessment = $assessments->firstWhere('assessment_id', (int) $request->query('assessment_id'));
        $selectedSubjectId = (int) old(
            'subject_id',
            $selectedAssessment?->subject_id ?? $request->query('subject_id')
        );

        return view('instructor.assessment-publish', $this->sharedData($user, 'assessments') + [
            'instructorProfile' => $instructorProfile,
            'handledSubjects' => $handledSubjects,
            'assessments' => $assessments,
            'classes' => $classes,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedAssessmentId' => (int) old('assessment_id', $selectedAssessment?->assessment_id),
        ]);
    }

    public function showAssessment(Assessment $assessment): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $ownedAssessment->load([
            'subject',
            'items.choices',
            'classAssessments.class',
        ])->loadCount('items', 'classAssessments');

        $publishableClasses = $instructorProfile
            ? $instructorProfile->classes()
                ->with('subject')
                ->where('subject_id', $ownedAssessment->subject_id)
                ->whereNull('archived_at')
                ->orderBy('class_name')
                ->get()
            : collect();

        return view('instructor.assessment-show', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedAssessment,
            'publishableClasses' => $publishableClasses,
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

        return redirect()
            ->route('instructor.assessments.show', $assessment)
            ->with('status', 'Assessment saved as draft. You can now add items.');
    }

    public function updateAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

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

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Assessment details updated.');
    }

    public function destroyAssessment(Request $request, Assessment $assessment): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        if ($ownedAssessment->status !== Assessment::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'assessment' => 'Only draft assessments can be deleted.',
            ]);
        }

        $assessmentId = $ownedAssessment->assessment_id;
        $assessmentTitle = $ownedAssessment->title;

        DB::transaction(function () use ($ownedAssessment) {
            $ownedAssessment->classAssessments()->delete();
            $ownedAssessment->items()->delete();
            $ownedAssessment->delete();
        });

        Log::warning('Draft assessment deleted by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $assessmentId,
            'assessment_title' => $assessmentTitle,
            'instructor_profile_id' => $instructorProfile?->instructor_profile_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Draft assessment deleted successfully.']);
        }

        return redirect()
            ->route('instructor.assessments', ['tab' => 'draft'])
            ->with('status', 'Draft assessment deleted successfully.');
    }

    public function storeAssessmentItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        $validated = $request->validate([
            'item_type' => ['required', 'string', Rule::in(array_keys($this->itemTypes()))],
            'points' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.question_text' => ['required', 'string', 'max:4000'],
            'items.*.choices' => ['nullable', 'array', 'max:6'],
            'items.*.choices.*' => ['nullable', 'string', 'max:1000'],
            'items.*.correct_choice' => ['nullable', 'integer', 'min:0', 'max:5'],
            'items.*.true_false_answer' => ['nullable', Rule::in(['true', 'false'])],
            'items.*.accepted_answer' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($validated['items'] as $index => $itemData) {
            $choices = collect($itemData['choices'] ?? [])
                ->map(fn ($choice) => trim((string) $choice))
                ->filter()
                ->values();

            if ($validated['item_type'] === 'multiple_choice' && $choices->count() < 2) {
                throw ValidationException::withMessages([
                    "items.{$index}.choices" => 'Multiple choice items need at least two choices.',
                ]);
            }

            if ($validated['item_type'] === 'multiple_choice' && ! $choices->has((int) ($itemData['correct_choice'] ?? -1))) {
                throw ValidationException::withMessages([
                    "items.{$index}.correct_choice" => 'Please select the correct choice.',
                ]);
            }

            if ($validated['item_type'] === 'true_false' && empty($itemData['true_false_answer'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.true_false_answer" => 'Please select True or False as the correct answer.',
                ]);
            }

            if ($validated['item_type'] === 'identification' && blank($itemData['accepted_answer'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.{$index}.accepted_answer" => 'Please enter the accepted answer for identification.',
                ]);
            }
        }

        DB::transaction(function () use ($ownedAssessment, $validated) {
            $nextOrder = ((int) $ownedAssessment->items()->max('sort_order')) + 1;

            foreach ($validated['items'] as $itemData) {
                $item = $ownedAssessment->items()->create([
                    'question_text' => $itemData['question_text'],
                    'item_type' => $validated['item_type'],
                    'points' => $validated['points'],
                    'is_required' => true,
                    'sort_order' => $nextOrder,
                ]);

                $nextOrder++;

                if ($validated['item_type'] === 'multiple_choice') {
                    $choices = collect($itemData['choices'] ?? [])
                        ->map(fn ($choice) => trim((string) $choice))
                        ->filter()
                        ->values();
                    $correctChoice = (int) ($itemData['correct_choice'] ?? -1);

                    foreach ($choices as $index => $choiceText) {
                        $item->choices()->create([
                            'choice_text' => $choiceText,
                            'is_correct' => $index === $correctChoice,
                            'sort_order' => $index + 1,
                        ]);
                    }
                }

                if ($validated['item_type'] === 'true_false') {
                    foreach (['true' => 'True', 'false' => 'False'] as $value => $label) {
                        $item->choices()->create([
                            'choice_text' => $label,
                            'is_correct' => ($itemData['true_false_answer'] ?? null) === $value,
                            'sort_order' => $value === 'true' ? 1 : 2,
                        ]);
                    }
                }

                if ($validated['item_type'] === 'identification') {
                    $item->choices()->create([
                        'choice_text' => trim((string) $itemData['accepted_answer']),
                        'is_correct' => true,
                        'sort_order' => 1,
                    ]);
                }
            }
        });

        Log::info('Assessment items added by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'item_type' => $validated['item_type'],
            'item_count' => count($validated['items']),
        ]);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', count($validated['items']).' question'.(count($validated['items']) === 1 ? '' : 's').' added.');
    }

    public function publishAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedAssessment = $this->ownedAssessment($assessment, $instructorProfile);

        if (! $ownedAssessment->items()->exists()) {
            return redirect()
                ->route('instructor.assessments.show', $ownedAssessment)
                ->withErrors(['publish' => 'Add at least one item before publishing this assessment.']);
        }

        $validated = $request->validate([
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:available_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'warning_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'display_mode' => ['required', 'string', Rule::in([
                ClassAssessment::DISPLAY_ALL_QUESTIONS,
                ClassAssessment::DISPLAY_ONE_QUESTION,
            ])],
            'score_visibility' => ['nullable', 'boolean'],
            'answer_visibility' => ['nullable', 'boolean'],
            'prevent_copy_paste' => ['nullable', 'boolean'],
            'detect_tab_switch' => ['nullable', 'boolean'],
            'screenshot_protection' => ['nullable', 'boolean'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $this->publishAssessmentToClasses($request, $user, $instructorProfile, $ownedAssessment, $validated);

        return redirect()
            ->route('instructor.assessments.show', $ownedAssessment)
            ->with('status', 'Assessment published to selected classes.');
    }

    public function publishSelectedAssessment(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before publishing assessments.');

        $handledSubjectIds = $this->handledSubjects($instructorProfile)->pluck('subject_id')->all();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', Rule::in($handledSubjectIds)],
            'assessment_id' => ['required', 'integer'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:available_at'],
            'attempt_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'warning_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'display_mode' => ['required', 'string', Rule::in([
                ClassAssessment::DISPLAY_ALL_QUESTIONS,
                ClassAssessment::DISPLAY_ONE_QUESTION,
            ])],
            'score_visibility' => ['nullable', 'boolean'],
            'answer_visibility' => ['nullable', 'boolean'],
            'prevent_copy_paste' => ['nullable', 'boolean'],
            'detect_tab_switch' => ['nullable', 'boolean'],
            'screenshot_protection' => ['nullable', 'boolean'],
            'shuffle_items' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $ownedAssessment = $instructorProfile->assessments()
            ->where('subject_id', $validated['subject_id'])
            ->where('assessment_id', $validated['assessment_id'])
            ->first();

        if (! $ownedAssessment) {
            throw ValidationException::withMessages([
                'assessment_id' => 'Select one of your assessments under the chosen subject.',
            ]);
        }

        if (! $ownedAssessment->items()->exists()) {
            throw ValidationException::withMessages([
                'assessment_id' => 'Add at least one item before publishing this assessment.',
            ]);
        }

        $this->publishAssessmentToClasses($request, $user, $instructorProfile, $ownedAssessment, $validated);

        return redirect()
            ->route('instructor.assessments')
            ->with('status', 'Assessment published to selected classes.');
    }

    private function publishAssessmentToClasses(
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

    private function sharedData(User $user, string $activeNav): array
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

    private function navItems(string $activeNav): array
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

    private function viewSwitches(User $user, string $activeMode): array
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

    private function assessmentTypes(): array
    {
        return [
            'quiz' => 'Quiz',
            'exam' => 'Exam',
            'activity' => 'Activity',
            'assignment' => 'Assignment',
        ];
    }

    private function itemTypes(): array
    {
        return [
            'multiple_choice' => 'Multiple Choice',
            'identification' => 'Identification',
            'essay' => 'Essay',
            'true_false' => 'True/False',
        ];
    }

    private function reportCategories(): array
    {
        return [
            'formative' => 'Formative',
            'summative' => 'Summative',
        ];
    }

    private function reportingTerms(): array
    {
        return [
            'midterm' => 'Midterm',
            'final' => 'Final',
        ];
    }

    /**
     * @param  Collection<int, ClassAssessment>  $classAssessments
     * @return Collection<int, array<string, mixed>>
     */
    private function reportSheetRows(Collection $classAssessments, string $reportType): Collection
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

    /**
     * @param  Collection<int, ClassAssessment>  $classAssessments
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function reportSheetMeta(Collection $classAssessments, Collection $rows): array
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

    /**
     * @return array<string, mixed>
     */
    private function classAssessmentReportAnalytics(ClassAssessment $classAssessment): array
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

    /**
     * @return array<string, mixed>
     */
    private function classPerformanceSummary(AcademicClass $class): array
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

    /**
     * @return Collection<int, array{has_results: bool, percentage: float|null, passed: bool|null}>
     */
    private function studentPerformanceByStudent(AcademicClass $class): Collection
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

    private function submissionScore(Submission $submission, Collection $items): float
    {
        $answers = $submission->answers->keyBy('assessment_item_id');

        return (float) $items->sum(function ($item) use ($answers): float {
            $answer = $answers->get($item->assessment_item_id);

            return $answer && $this->isSubmissionAnswerCorrect($item, $answer)
                ? (float) $item->points
                : 0.0;
        });
    }

    private function isSubmissionAnswerCorrect($item, $answer): bool
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

    private function formatReportNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * @return Collection<int, ClassAssessment>
     */
    private function completedReportableAssessments(?InstructorProfile $instructorProfile): Collection
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

    /**
     * @return Collection<int, Subject>
     */
    private function handledSubjects(?InstructorProfile $instructorProfile): Collection
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

    private function ownedAssessment(Assessment $assessment, ?InstructorProfile $instructorProfile): Assessment
    {
        abort_unless(
            $instructorProfile && $assessment->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to manage this assessment.'
        );

        return $assessment;
    }

    private function ownedClassAssessment(ClassAssessment $classAssessment, ?InstructorProfile $instructorProfile): ClassAssessment
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

    private function classTabs(AcademicClass $class, string $activeTab): array
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

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    private function instructorProfile(User $user): ?InstructorProfile
    {
        return $user->instructorProfile;
    }

    private function ownedClass(AcademicClass $class, ?InstructorProfile $instructorProfile): AcademicClass
    {
        abort_unless(
            $instructorProfile && $class->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access this class.'
        );

        return $class;
    }

    private function ensureActiveClass(AcademicClass $class): void
    {
        if (! $class->archived_at) {
            return;
        }

        throw ValidationException::withMessages([
            'class' => 'Archived classes are records only. Restore the class before making changes.',
        ]);
    }

    private function ensureClassJoinAccess(AcademicClass $class): void
    {
        if ($class->join_token && $class->join_code) {
            return;
        }

        $class->forceFill(array_filter([
            'join_token' => $class->join_token ?: $this->generateClassJoinToken(),
            'join_code' => $class->join_code ?: $this->generateClassJoinCode(),
        ]))->save();
    }

    private function generateClassJoinToken(): string
    {
        do {
            $token = Str::random(40);
        } while (AcademicClass::query()->where('join_token', $token)->exists());

        return $token;
    }

    private function generateClassJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (AcademicClass::query()->where('join_code', $code)->exists());

        return $code;
    }

    private function markJoinRequestApproved(AcademicClass $class, StudentProfile $studentProfile, int $responderId): void
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

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return list<string>
     */
    private function extractStudentNumbersFromRows(array $rows): array
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

    /**
     * @param  list<string>  $studentNumbers
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     summary: array<string, int>,
     *     ready_student_profile_ids: list<int>
     * }
     */
    private function buildImportPreview(AcademicClass $class, array $studentNumbers): array
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

    private function pullImportPreview(Request $request, AcademicClass $class): ?array
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
