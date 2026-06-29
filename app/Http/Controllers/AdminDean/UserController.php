<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    public function store(Request $request): RedirectResponse
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
            'base_role' => ['required', Rule::in(['instructor', 'student'])],
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

        $createdUser = DB::transaction(function () use ($validated, $user, $scopedCollege) {
            $createdUser = User::create([
                'name' => $this->buildName($validated),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'],
            ]);

            $role = Role::where('role_name', $validated['base_role'])->firstOrFail();
            $createdUser->roles()->attach($role->role_id);

            if ($validated['base_role'] === 'instructor') {
                InstructorProfile::create([
                    'user_id' => $createdUser->id,
                    'department_id' => $validated['department_id'],
                    'employee_number' => $validated['employee_number'],
                ]);
            }

            if ($validated['base_role'] === 'student') {
                StudentProfile::create([
                    'user_id' => $createdUser->id,
                    'program_id' => $validated['program_id'],
                    'student_number' => $validated['student_number'],
                ]);
            }

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

        app(NotificationService::class)->send(
            $createdUser,
            'Account Created',
            'Your AIssessment account has been created.',
            $createdUser->portalRouteName() ? route($createdUser->portalRouteName()) : route('login'),
            'account'
        );

        return redirect()
            ->back()
            ->with('status', 'User account created successfully.');
    }
}
