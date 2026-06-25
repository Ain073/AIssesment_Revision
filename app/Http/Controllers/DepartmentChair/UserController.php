<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\InstructorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
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
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $programIds = $scopedPrograms->pluck('program_id')->all();

        abort_unless($scopedDepartment, 403, 'Department Chair account needs an assigned department before creating users.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(['instructor', 'student'])],
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

        DB::transaction(function () use ($validated, $user, $scopedDepartment) {
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
                    'department_id' => $scopedDepartment->department_id,
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

            Log::info('User account created by department chair.', [
                'actor_id' => $user->id,
                'user_id' => $createdUser->id,
                'base_role' => $validated['base_role'],
                'department_id' => $scopedDepartment->department_id,
                'program_id' => $validated['program_id'] ?? null,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'User account created successfully.');
    }
}
