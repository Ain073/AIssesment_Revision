<?php

namespace App\Http\Controllers\Student;

use App\Models\AcademicClass;
use App\Models\ClassJoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClassController extends BaseController
{
    public function classes(): View
    {
        $user = $this->currentUser();

        return view('student.classes', $this->sharedData($user, 'classes') + $this->studentClassesData($user));
    }

    public function classesLive(): View
    {
        return view('student.partials.classes-live', $this->studentClassesData($this->currentUser()));
    }

    public function classRequestsLive(): View
    {
        return view('student.partials.join-requests-table', $this->studentClassesData($this->currentUser()));
    }

    public function showClassJoinLink(string $token): View|RedirectResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $class = AcademicClass::query()
            ->with(['subject', 'instructorProfile.user', 'instructorProfile.department.college'])
            ->where('join_token', $token)
            ->whereNull('archived_at')
            ->firstOrFail();

        $existingRequest = ClassJoinRequest::query()
            ->where('class_id', $class->class_id)
            ->where('student_profile_id', $studentProfile->student_profile_id)
            ->first();
        $alreadyEnrolled = $class->students()
            ->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)
            ->exists();

        return view('student.class-join', $this->sharedData($user, 'classes') + [
            'class' => $class,
            'studentProfile' => $studentProfile,
            'existingRequest' => $existingRequest,
            'alreadyEnrolled' => $alreadyEnrolled,
        ]);
    }

    public function requestClassJoin(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $class = AcademicClass::query()
            ->where('join_token', $token)
            ->whereNull('archived_at')
            ->firstOrFail();

        return $this->submitClassJoinRequest($request, $class, $studentProfile, $user, 'link');
    }

    public function requestClassJoinByCode(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->currentUser();
        $studentProfile = $this->studentProfileOrRedirect($user);

        if ($studentProfile instanceof RedirectResponse) {
            return $studentProfile;
        }

        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:20'],
        ]);

        $joinCode = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $validated['join_code']));

        $class = AcademicClass::query()
            ->where('join_code', $joinCode)
            ->whereNull('archived_at')
            ->first();

        if (! $class) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No class was found for that join code.',
                    'errors' => [
                        'join_code' => ['No class was found for that join code.'],
                    ],
                ], 422);
            }

            return redirect()
                ->route('student.classes')
                ->withErrors(['join_code' => 'No class was found for that join code.'])
                ->withInput();
        }

        return $this->submitClassJoinRequest($request, $class, $studentProfile, $user, 'code');
    }
}
