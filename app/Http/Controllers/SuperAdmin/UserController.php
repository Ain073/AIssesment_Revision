<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\StudentAccountImportService;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request, StudentAccountImportService $importer): View
    {
        $users = User::with(['roles', 'instructorProfile.department.college', 'studentProfile.program.department.college'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        $departments = Department::with('college')
            ->orderBy('college_id')
            ->orderBy('dept_name')
            ->get();

        $programs = Program::with('department.college')
            ->orderBy('college_id')
            ->orderBy('department_id')
            ->orderBy('program_name')
            ->get();

        $teachers = $users->filter->hasRole('instructor')->values();
        $students = $users->filter->hasRole('student')->values();
        $adminDeans = $teachers->filter->hasRole('admin_dean')->values();
        $departmentChairs = $teachers->filter->hasRole('department_chair')->values();

        return view('super-admin.users.index', [
            'users' => $users,
            'teachers' => $teachers,
            'students' => $students,
            'adminDeans' => $adminDeans,
            'departmentChairs' => $departmentChairs,
            'departments' => $departments,
            'programs' => $programs,
            'studentImportPreview' => $importer->previewForRequest($request, 'super-admin'),
        ]);
    }

    public function downloadStudentImportSample(StudentAccountImportService $importer): StreamedResponse
    {
        $program = Program::query()->orderBy('program_name')->first();

        abort_unless($program, 404, 'No program is available for student account import.');

        return $importer->sampleCsv();
    }

    public function previewStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $programs = Program::query()->orderBy('program_name')->get();

        abort_if($programs->isEmpty(), 403, 'A program is required before importing students.');

        $token = $importer->previewUpload($request, $programs, 'super-admin');

        return redirect()->route('super-admin.users', ['import_token' => $token]);
    }

    public function confirmStudentImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $programs = Program::query()->orderBy('program_name')->get();

        abort_if($programs->isEmpty(), 403);

        $result = $importer->confirm($request, $programs, 'super-admin');
        $redirect = redirect()
            ->route('super-admin.users')
            ->with('status', $result['created_count'].' student accounts created successfully.');

        if ($result['initial_password_emails_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some initial password emails were not delivered.');
        }

        return $redirect;
    }

    public function store(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(UserAccountService::BASE_ROLES)],
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
                UserAccountService::identifierPasswordDigitsRule(),
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
                UserAccountService::identifierPasswordDigitsRule(),
                'unique:student_profiles,student_number',
            ],
            'authorizations' => ['nullable', 'array'],
            'authorizations.*' => [Rule::in(['admin_dean'])],
            'form_mode' => ['nullable', 'string'],
        ]);

        $managedRoles = collect($validated['authorizations'] ?? [])
            ->intersect(['admin_dean'])
            ->unique()
            ->values();

        if ($managedRoles->isNotEmpty() && $validated['base_role'] !== 'instructor') {
            return redirect()
                ->route('super-admin.users')
                ->withInput()
                ->withErrors(new MessageBag([
                    'authorizations' => 'Only teacher accounts can receive a designation.',
                ]));
        }

        $initialPassword = $accounts->initialPasswordFor($validated);
        $validated['password'] = $initialPassword;

        $createdUser = DB::transaction(function () use ($validated, $accounts, $managedRoles) {
            $user = $accounts->createAccount($validated, $managedRoles->all());

            Log::info('User account created by super admin.', [
                'actor_id' => Auth::id(),
                'user_id' => $user->id,
                'email' => $user->email,
                'base_role' => $validated['base_role'],
                'authorizations' => $managedRoles->all(),
                'department_id' => $validated['department_id'] ?? null,
                'employee_number' => $validated['employee_number'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'student_number' => $validated['student_number'] ?? null,
            ]);

            return $user;
        });

        $accounts->sendAccountCreatedNotification($createdUser);
        $passwordEmailSent = $accounts->sendInitialPasswordEmail($createdUser, $initialPassword);

        $redirect = redirect()
            ->route('super-admin.users')
            ->with('status', 'User account created successfully.');

        if (! $passwordEmailSent) {
            $redirect->with('mail_warning', 'Account was created, but the initial password email was not delivered.');
        }

        return $redirect;
    }

    public function update(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return redirect()
                ->route('super-admin.users')
                ->withErrors(new MessageBag(['user' => 'Admin account cannot be edited here.']));
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'base_role' => ['required', Rule::in(UserAccountService::BASE_ROLES)],
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
            'authorizations.*' => [Rule::in(['admin_dean'])],
            'form_mode' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $selectedManagedRoles = collect($validated['authorizations'] ?? [])
            ->intersect(['admin_dean'])
            ->unique()
            ->values();

        if ($selectedManagedRoles->contains('admin_dean') && $user->hasRole('department_chair')) {
            return redirect()
                ->route('super-admin.users')
                ->withInput()
                ->withErrors(new MessageBag([
                    'authorizations' => 'This teacher already has a Department Chair designation. Ask the Dean to remove it before assigning Dean designation.',
                ]));
        }

        $managedRoles = $selectedManagedRoles;

        if ($user->hasRole('department_chair')) {
            $managedRoles = $managedRoles->push('department_chair');
        }

        DB::transaction(function () use ($user, $validated, $accounts, $managedRoles) {
            $accounts->updateAccount($user, $validated, $managedRoles->all());

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
                ->withErrors(new MessageBag(['user' => 'Admin account cannot be deleted.']));
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

}
