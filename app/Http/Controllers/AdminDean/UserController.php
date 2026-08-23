<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\Program;
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
        $scopedCollege = $this->scopedCollege($user);

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating users.');

        $departmentIds = Department::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('department_id')
            ->all();
        $programIds = Program::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('program_id')
            ->all();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(UserAccountService::BASE_ROLES)],
            'department_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'integer',
                Rule::in($departmentIds),
            ],
            'employee_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'string',
                'max:255',
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
                'unique:student_profiles,student_number',
            ],
        ]);

        $createdUser = DB::transaction(function () use ($validated, $user, $scopedCollege, $accounts) {
            $createdUser = $accounts->createAccount($validated);

            Log::info('User account created by admin/dean.', [
                'actor_id' => $user->id,
                'user_id' => $createdUser->id,
                'base_role' => $validated['base_role'],
                'college_id' => $scopedCollege->college_id,
                'department_id' => $validated['department_id'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
            ]);

            return $createdUser;
        });

        $accounts->sendAccountCreatedNotification($createdUser);

        return redirect()
            ->back()
            ->with('status', 'User account created successfully.');
    }

    public function update(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $actor = $this->currentUser();
        $scopedCollege = $this->scopedCollege($actor);

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before updating users.');

        $targetUser = $this->scopedUser($user, $scopedCollege->college_id);
        $targetBaseRole = $this->baseRoleFor($targetUser);

        abort_unless($targetBaseRole, 403, 'Only teacher and student accounts can be updated here.');

        $departmentIds = Department::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('department_id')
            ->all();
        $programIds = Program::query()
            ->where('college_id', $scopedCollege->college_id)
            ->pluck('program_id')
            ->all();

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
            'department_id' => [
                Rule::requiredIf($targetBaseRole === 'instructor'),
                'nullable',
                'integer',
                Rule::in($departmentIds),
            ],
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

        $managedRoles = $targetUser->roles
            ->pluck('role_name')
            ->intersect(UserAccountService::MANAGED_ROLES)
            ->values()
            ->all();

        DB::transaction(function () use ($targetUser, $validated, $actor, $scopedCollege, $accounts, $managedRoles) {
            $accounts->updateAccount($targetUser, $validated, $managedRoles);

            Log::info('User account updated by admin/dean.', [
                'actor_id' => $actor->id,
                'user_id' => $targetUser->id,
                'base_role' => $validated['base_role'],
                'college_id' => $scopedCollege->college_id,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'User account updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $actor = $this->currentUser();
        $scopedCollege = $this->scopedCollege($actor);

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before deleting users.');

        $targetUser = $this->scopedUser($user, $scopedCollege->college_id);

        if ($targetUser->is($actor)) {
            return redirect()
                ->back()
                ->withErrors(['user' => 'You cannot delete your own account.']);
        }

        DB::transaction(function () use ($targetUser, $actor, $scopedCollege) {
            Log::info('User account deleted by admin/dean.', [
                'actor_id' => $actor->id,
                'user_id' => $targetUser->id,
                'college_id' => $scopedCollege->college_id,
            ]);

            $targetUser->delete();
        });

        return redirect()
            ->back()
            ->with('status', 'User account deleted successfully.');
    }

    private function scopedUser(User $user, int $collegeId): User
    {
        $user->loadMissing(['roles', 'instructorProfile.department', 'studentProfile.program']);

        $isCollegeInstructor = $user->hasRole('instructor')
            && (int) $user->instructorProfile?->department?->college_id === $collegeId;
        $isCollegeStudent = $user->hasRole('student')
            && (int) $user->studentProfile?->program?->college_id === $collegeId;

        abort_unless($isCollegeInstructor || $isCollegeStudent, 403);

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
