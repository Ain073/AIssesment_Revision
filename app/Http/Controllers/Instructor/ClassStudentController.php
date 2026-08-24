<?php

namespace App\Http\Controllers\Instructor;

use App\Models\AcademicClass;
use App\Models\ClassJoinRequest;
use App\Models\StudentProfile;
use App\Services\NotificationService;
use App\Services\TabularFileReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassStudentController extends BaseController
{
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
            ->with(['user.roles', 'program.department.college'])
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

        if ($studentProfile->user) {
            app(NotificationService::class)->send(
                $studentProfile->user,
                'Added to Class',
                'You were added to '.$ownedClass->class_name.'.',
                route('student.classes'),
                'class'
            );
        }

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

        if ($attachIds->isNotEmpty()) {
            $students = StudentProfile::query()
                ->with('user')
                ->whereIn('student_profile_id', $attachIds->all())
                ->get()
                ->pluck('user')
                ->filter();

            app(NotificationService::class)->sendToMany(
                $students,
                'Added to Class',
                'You were added to '.$ownedClass->class_name.'.',
                route('student.classes'),
                'class'
            );
        }

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

        app(NotificationService::class)->send(
            $studentProfile->user,
            'Join Request Approved',
            'Your request to join '.$ownedClass->class_name.' was approved.',
            route('student.classes'),
            'class'
        );

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

        $joinRequest->loadMissing('studentProfile.user');

        Log::info('Class join request rejected by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'class_join_request_id' => $joinRequest->class_join_request_id,
        ]);

        if ($joinRequest->studentProfile?->user) {
            app(NotificationService::class)->send(
                $joinRequest->studentProfile->user,
                'Join Request Rejected',
                'Your request to join '.$ownedClass->class_name.' was rejected.',
                route('student.classes'),
                'class'
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Student join request rejected.']);
        }

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student join request rejected.');
    }
}
