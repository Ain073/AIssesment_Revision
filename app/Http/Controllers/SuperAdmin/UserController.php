<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    private const BASE_ROLES = [
        'instructor',
        'student',
    ];

    private const MANAGED_ROLES = [
        'admin_dean',
        'department_chair',
    ];

    public function index(): View
    {
        $users = User::with(['roles', 'instructorProfile.department.college', 'studentProfile.program.college'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        $departments = Department::with('college')
            ->orderBy('college_id')
            ->orderBy('dept_name')
            ->get();

        $programs = Program::with('college')
            ->orderBy('college_id')
            ->orderBy('program_name')
            ->get();

        $teachers = $users->filter->hasRole('instructor')->values();
        $students = $users->filter->hasRole('student')->values();
        $adminDeans = $teachers->filter->hasRole('admin_dean')->values();
        $departmentChairs = $teachers->filter->hasRole('department_chair')->values();

        return view('super-admin.users', [
            'users' => $users,
            'totalUsers' => $users->count(),
            'teachers' => $teachers,
            'students' => $students,
            'adminDeans' => $adminDeans,
            'departmentChairs' => $departmentChairs,
            'totalTeachers' => $teachers->count(),
            'totalStudents' => $students->count(),
            'totalAdminDeans' => $adminDeans->count(),
            'totalDepartmentChairs' => $departmentChairs->count(),
            'departments' => $departments,
            'programs' => $programs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(self::BASE_ROLES)],
            'department_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'integer',
                Rule::exists('departments', 'department_id'),
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
                Rule::exists('programs', 'program_id'),
            ],
            'student_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'string',
                'max:255',
                'unique:student_profiles,student_number',
            ],
            'form_mode' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $this->buildName($validated),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'],
            ]);

            $this->syncUserRoles($user, $validated['base_role']);
            $this->syncInstructorProfile(
                $user,
                $validated['base_role'],
                $validated['department_id'] ?? null,
                $validated['employee_number'] ?? null,
            );
            $this->syncStudentProfile(
                $user,
                $validated['base_role'],
                $validated['program_id'] ?? null,
                $validated['student_number'] ?? null,
            );

            Log::info('User account created by super admin.', [
                'actor_id' => Auth::id(),
                'user_id' => $user->id,
                'email' => $user->email,
                'base_role' => $validated['base_role'],
                'department_id' => $validated['department_id'] ?? null,
                'employee_number' => $validated['employee_number'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'student_number' => $validated['student_number'] ?? null,
            ]);
        });

        return redirect()
            ->route('super-admin.users')
            ->with('status', 'User account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return redirect()
                ->route('super-admin.users')
                ->withErrors(new MessageBag(['user' => 'Super Admin account cannot be edited here.']));
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(self::BASE_ROLES)],
            'department_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'integer',
                Rule::exists('departments', 'department_id'),
            ],
            'employee_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'instructor'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('instructor_profiles', 'employee_number')->ignore($user->instructorProfile?->instructor_profile_id, 'instructor_profile_id'),
            ],
            'program_id' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'integer',
                Rule::exists('programs', 'program_id'),
            ],
            'student_number' => [
                Rule::requiredIf(fn () => $request->input('base_role') === 'student'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('student_profiles', 'student_number')->ignore($user->studentProfile?->student_profile_id, 'student_profile_id'),
            ],
            'authorizations' => ['nullable', 'array'],
            'authorizations.*' => [Rule::in(self::MANAGED_ROLES)],
            'form_mode' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->fill([
                'name' => $this->buildName($validated),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'status' => $validated['status'],
            ]);

            $user->save();

            $this->syncUserRoles($user, $validated['base_role'], $validated['authorizations'] ?? []);
            $this->syncInstructorProfile(
                $user,
                $validated['base_role'],
                $validated['department_id'] ?? null,
                $validated['employee_number'] ?? null,
            );
            $this->syncStudentProfile(
                $user,
                $validated['base_role'],
                $validated['program_id'] ?? null,
                $validated['student_number'] ?? null,
            );

            Log::info('User account updated by super admin.', [
                'actor_id' => Auth::id(),
                'user_id' => $user->id,
                'email' => $user->email,
                'base_role' => $validated['base_role'],
                'department_id' => $validated['department_id'] ?? null,
                'employee_number' => $validated['employee_number'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'student_number' => $validated['student_number'] ?? null,
                'authorizations' => $validated['authorizations'] ?? [],
                'status' => $validated['status'],
            ]);
        });

        return redirect()
            ->route('super-admin.users')
            ->with('status', 'User account updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return redirect()
                ->route('super-admin.users')
                ->withErrors(new MessageBag(['user' => 'Super Admin account cannot be deleted.']));
        }

        Log::warning('User account deleted by super admin.', [
            'actor_id' => Auth::id(),
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $user->delete();

        return redirect()
            ->route('super-admin.users')
            ->with('status', 'User account deleted successfully.');
    }

    /**
     * Build a readable fallback name for places that still rely on the legacy name column.
     *
     * @param  array<string, mixed>  $validated
     */
    private function buildName(array $validated): string
    {
        return collect([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ])->filter()->implode(' ');
    }

    private function syncUserRoles(User $user, string $baseRole, array $selectedManagedRoles = []): void
    {
        $selectedManagedRoles = collect($selectedManagedRoles)->unique()->values();

        $roleIds = Role::query()
            ->whereIn('role_name', [...self::BASE_ROLES, ...self::MANAGED_ROLES])
            ->pluck('role_id', 'role_name');

        $currentRoleIds = $user->roles()->pluck('roles.role_id');
        $baseRoleIds = $roleIds->only(self::BASE_ROLES)->values();
        $elevatedRoleIds = $roleIds->only(self::MANAGED_ROLES)->values();
        $unmanagedRoleIds = $currentRoleIds->diff($baseRoleIds)->diff($elevatedRoleIds);
        $rolesToKeep = collect([$roleIds->get($baseRole)])->filter();

        if ($baseRole === 'instructor') {
            $rolesToKeep = $rolesToKeep->merge(
                $selectedManagedRoles
                    ->map(fn (string $roleName) => $roleIds->get($roleName))
                    ->filter()
                    ->values()
            );
        }

        $user->roles()->sync($unmanagedRoleIds->merge($rolesToKeep)->unique()->all());
    }

    private function syncInstructorProfile(
        User $user,
        string $baseRole,
        ?int $departmentId,
        ?string $employeeNumber
    ): void
    {
        if ($baseRole !== 'instructor') {
            $user->instructorProfile()?->delete();

            return;
        }

        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $departmentId,
                'employee_number' => $employeeNumber,
            ],
        );
    }

    private function syncStudentProfile(
        User $user,
        string $baseRole,
        ?int $programId,
        ?string $studentNumber
    ): void
    {
        if ($baseRole !== 'student') {
            $user->studentProfile()?->delete();

            return;
        }

        StudentProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'program_id' => $programId,
                'student_number' => $studentNumber,
            ],
        );
    }
}
