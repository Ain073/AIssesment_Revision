<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Support\RecordSearch;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StudentAccountImportService;
use App\Services\UserAccountService;
use App\Services\UserAccountAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function checkAvailability(Request $request, UserAccountAvailability $availability, ?User $user = null): JsonResponse
    {
        abort_if($user?->hasRole('super_admin'), 403, 'Admin account cannot be edited here.');

        return $availability->check($request, $user);
    }

    public function index(Request $request, StudentAccountImportService $importer): View
    {
        $departmentKey = $request->query('department') ?? $request->query('department_id');
        $selectedDepartment = null;

        if (filled($departmentKey)) {
            $selectedDepartment = Department::query()
                ->where('public_id', $departmentKey)
                ->orWhere('department_id', $departmentKey)
                ->first();
        }

        $programKey = $request->query('program') ?? $request->query('program_id');
        $selectedProgram = null;

        if (filled($programKey)) {
            $selectedProgram = Program::query()
                ->where('public_id', $programKey)
                ->orWhere('program_id', $programKey)
                ->first();
        }

        $selectedDepartmentId = $selectedDepartment?->department_id;
        $selectedProgramId = $selectedProgram?->program_id;

        $usersQuery = User::with(['roles', 'instructorProfile.department.college', 'studentProfile.program.department.college'])
            ->tap(fn ($query) => RecordSearch::apply($query, RecordSearch::USER_FIELDS))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->orderBy('users.id');

        $teachersQuery = (clone $usersQuery)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when($selectedDepartmentId, fn ($query) => $query->whereHas(
                'instructorProfile',
                fn ($profile) => $profile->where('department_id', $selectedDepartmentId),
            ));

        $studentsQuery = (clone $usersQuery)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'student'))
            ->when($selectedProgramId, fn ($query) => $query->whereHas(
                'studentProfile',
                fn ($profile) => $profile->where('program_id', $selectedProgramId),
            ));

        $teachers = $this->paginateUsers($teachersQuery, 'teachers_page')->appends(['tab' => 'teachers']);
        $students = $this->paginateUsers($studentsQuery, 'students_page')->appends(['tab' => 'students']);
        $users = $teachers->getCollection()->merge($students->getCollection());

        $departments = Department::with('college')
            ->orderBy('college_id')
            ->orderBy('dept_name')
            ->get();

        $programs = Program::with('department.college')
            ->orderBy(
                Department::select('college_id')
                    ->whereColumn('departments.department_id', 'programs.department_id')
            )
            ->orderBy('department_id')
            ->orderBy('program_name')
            ->get();

        return view('super-admin.users.index', [
            'users' => $users,
            'teachers' => $teachers,
            'students' => $students,
            'departments' => $departments,
            'programs' => $programs,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDepartmentKey' => $selectedDepartment?->public_id ?? ($selectedDepartment ? (string) $selectedDepartment->department_id : ''),
            'selectedProgramKey' => $selectedProgram?->public_id ?? ($selectedProgram ? (string) $selectedProgram->program_id : ''),
            'studentImportPreview' => $importer->previewForRequest($request, 'super-admin'),
        ]);
    }

    public function downloadStudentImportSample(StudentAccountImportService $importer): StreamedResponse|RedirectResponse
    {
        $program = Program::query()->orderBy('program_name')->first();

        if (! $program) {
            return redirect()
                ->route('super-admin.users')
                ->withErrors(['student_import' => 'Add at least one program before downloading the student import sample.']);
        }

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

    public function store(Request $request, UserAccountService $accounts): JsonResponse|RedirectResponse
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
            'designation' => ['nullable', Rule::in(['instructor', 'admin_dean'])],
            'authorizations' => ['nullable', 'array'],
            'authorizations.*' => [Rule::in(['admin_dean'])],
            'form_mode' => ['nullable', 'string'],
        ]);

        $selectedAuthorizations = ($validated['designation'] ?? 'instructor') === 'admin_dean'
            ? ['admin_dean']
            : ($validated['authorizations'] ?? []);

        $managedRoles = collect($selectedAuthorizations)
            ->intersect(['admin_dean'])
            ->unique()
            ->values();

        if ($managedRoles->isNotEmpty() && $validated['base_role'] !== 'instructor') {
            throw ValidationException::withMessages([
                'authorizations' => 'Only teacher accounts can receive a designation.',
            ]);
        }

        if ($managedRoles->contains('admin_dean') && $this->collegeHasDeanForDepartment((int) $validated['department_id'])) {
            throw ValidationException::withMessages([
                'authorizations' => 'This college already has a Dean. Remove the current designation first.',
            ]);
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

        AuditLogger::log('CREATE', 'Users', "Created {$validated['base_role']} account for {$createdUser->displayName()} ({$createdUser->email})", $createdUser);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User account created successfully.',
                'warning' => $passwordEmailSent ? null : 'Account was created, but the initial password email was not delivered.',
            ], 201);
        }

        $redirect = redirect()
            ->route('super-admin.users')
            ->with('status', 'User account created successfully.');

        if (! $passwordEmailSent) {
            $redirect->with('mail_warning', 'Account was created, but the initial password email was not delivered.');
        }

        return $redirect;
    }

    public function update(Request $request, User $user, UserAccountService $accounts): JsonResponse|RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            throw ValidationException::withMessages(['user' => 'Admin account cannot be edited here.']);
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
            throw ValidationException::withMessages([
                'authorizations' => 'This teacher already has a Department Chair designation. Ask the Dean to remove it before assigning Dean designation.',
            ]);
        }

        $managedRoles = $selectedManagedRoles;

        if ($user->hasRole('department_chair')) {
            $managedRoles = $managedRoles->push('department_chair');
        }

        if (
            $validated['base_role'] === 'instructor'
            && $managedRoles->contains('admin_dean')
            && $this->collegeHasDeanForDepartment((int) $validated['department_id'], $user->id)
        ) {
            throw ValidationException::withMessages([
                'authorizations' => 'This college already has a Dean. Remove the current designation first.',
            ]);
        }

        if (
            $validated['base_role'] === 'instructor'
            && $managedRoles->contains('department_chair')
            && $this->departmentHasChair((int) $validated['department_id'], $user->id)
        ) {
            throw ValidationException::withMessages([
                'authorizations' => 'This department already has a Department Chair. Remove the current designation first.',
            ]);
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

        AuditLogger::log('UPDATE', 'Users', "Updated {$validated['base_role']} account details (Status: {$validated['status']})", $user);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User account updated successfully.']);
        }

        return redirect()
            ->back(fallback: route('super-admin.users'))
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

    private function paginateUsers(Builder $query, string $pageName): LengthAwarePaginator
    {
        $users = $query->paginate(25, ['*'], $pageName);

        // An edit can remove the final record on a page from the current filters.
        if ($users->currentPage() > $users->lastPage()) {
            $users = $query->paginate(25, ['*'], $pageName, $users->lastPage(), $users->total());
        }

        return $users->withQueryString();
    }

    private function collegeHasDeanForDepartment(int $departmentId, ?int $exceptUserId = null): bool
    {
        $collegeId = Department::query()
            ->where('department_id', $departmentId)
            ->value('college_id');

        if (! $collegeId) {
            return false;
        }

        return User::query()
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'admin_dean'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->where('college_id', $collegeId)
            )
            ->exists();
    }

    private function departmentHasChair(int $departmentId, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
            ->whereHas('instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->exists();
    }
}
