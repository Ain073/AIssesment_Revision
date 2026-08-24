<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    public function store(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $programIds = $scopedPrograms->pluck('program_id')->all();

        abort_unless($scopedDepartment, 403, 'Department Chair account needs an assigned department before creating users.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(UserAccountService::BASE_ROLES)],
            'employee_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'string',
                'max:255',
                UserAccountService::identifierPasswordDigitsRule(),
                'unique:instructor_profiles,employee_number',
            ],
            'program_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'integer',
                Rule::in($programIds),
            ],
            'student_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'string',
                'max:255',
                UserAccountService::identifierPasswordDigitsRule(),
                'unique:student_profiles,student_number',
            ],
        ]);

        $validated['department_id'] = $scopedDepartment->department_id;
        $initialPassword = $accounts->initialPasswordFor($validated);
        $validated['password'] = $initialPassword;

        $createdUser = DB::transaction(function () use ($validated, $user, $scopedDepartment, $accounts) {
            $createdUser = $accounts->createAccount($validated);

            Log::info('User account created by department chair.', [
                'actor_id' => $user->id,
                'user_id' => $createdUser->id,
                'base_role' => $validated['base_role'],
                'department_id' => $scopedDepartment->department_id,
                'program_id' => $validated['program_id'] ?? null,
            ]);

            return $createdUser;
        });

        $accounts->sendAccountCreatedNotification($createdUser);
        $passwordEmailSent = $accounts->sendInitialPasswordEmail($createdUser, $initialPassword);

        $redirect = redirect()
            ->back()
            ->with('status', 'User account created successfully.');

        if (! $passwordEmailSent) {
            $redirect->with('mail_warning', 'Account was created, but the initial password email was not delivered.');
        }

        return $redirect;
    }

    public function update(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $actor = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($actor);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $programIds = $scopedPrograms->pluck('program_id')->all();

        abort_unless($scopedDepartment, 403, 'Department Chair account needs an assigned department before updating users.');

        $targetUser = $this->scopedUser($user, $scopedDepartment->department_id, $programIds);
        $targetBaseRole = $this->baseRoleFor($targetUser);

        abort_unless($targetBaseRole, 403, 'Only teacher and student accounts can be updated here.');

        $employeeNumberRule = Rule::unique('instructor_profiles', 'employee_number');
        if ($targetUser->instructorProfile) {
            $employeeNumberRule->ignore($targetUser->instructorProfile->instructor_profile_id, 'instructor_profile_id');
        }

        $studentNumberRule = Rule::unique('student_profiles', 'student_number');
        if ($targetUser->studentProfile) {
            $studentNumberRule->ignore($targetUser->studentProfile->student_profile_id, 'student_profile_id');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in([$targetBaseRole])],
            'employee_number' => [
                Rule::requiredIf($targetBaseRole === 'instructor'),
                'nullable',
                'string',
                'max:255',
                $employeeNumberRule,
            ],
            'program_id' => [
                Rule::requiredIf($targetBaseRole === 'student'),
                'nullable',
                'integer',
                Rule::in($programIds),
            ],
            'student_number' => [
                Rule::requiredIf($targetBaseRole === 'student'),
                'nullable',
                'string',
                'max:255',
                $studentNumberRule,
            ],
        ]);

        $validated['department_id'] = $scopedDepartment->department_id;

        $managedRoles = $targetUser->roles
            ->pluck('role_name')
            ->intersect(UserAccountService::MANAGED_ROLES)
            ->values()
            ->all();

        DB::transaction(function () use ($targetUser, $validated, $actor, $scopedDepartment, $accounts, $managedRoles) {
            $accounts->updateAccount($targetUser, $validated, $managedRoles);

            Log::info('User account updated by department chair.', [
                'actor_id' => $actor->id,
                'user_id' => $targetUser->id,
                'base_role' => $validated['base_role'],
                'department_id' => $scopedDepartment->department_id,
                'program_id' => $validated['program_id'] ?? null,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'User account updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $actor = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($actor);
        $programIds = $this->scopedPrograms($scopedDepartment)->pluck('program_id')->all();

        abort_unless($scopedDepartment, 403, 'Department Chair account needs an assigned department before deleting users.');

        $targetUser = $this->scopedUser($user, $scopedDepartment->department_id, $programIds);

        if ($targetUser->is($actor)) {
            return redirect()
                ->back()
                ->withErrors(['user' => 'You cannot delete your own account.']);
        }

        DB::transaction(function () use ($targetUser, $actor, $scopedDepartment) {
            Log::info('User account deleted by department chair.', [
                'actor_id' => $actor->id,
                'user_id' => $targetUser->id,
                'department_id' => $scopedDepartment->department_id,
            ]);

            $targetUser->delete();
        });

        return redirect()
            ->back()
            ->with('status', 'User account deleted successfully.');
    }

    private function scopedUser(User $user, int $departmentId, array $programIds): User
    {
        $user->loadMissing(['roles', 'instructorProfile.department', 'studentProfile.program']);

        $isDepartmentInstructor = $user->hasRole('instructor')
            && (int) $user->instructorProfile?->department_id === $departmentId;
        $isDepartmentStudent = $user->hasRole('student')
            && in_array((int) $user->studentProfile?->program_id, $programIds, true);

        abort_unless($isDepartmentInstructor || $isDepartmentStudent, 403);

        return $user;
    }

    private function baseRoleFor(User $user): ?string
    {
        if ($user->hasRole('instructor')) {
            return 'instructor';
        }

        if ($user->hasRole('student')) {
            return 'student';
        }

        return null;
    }
}
