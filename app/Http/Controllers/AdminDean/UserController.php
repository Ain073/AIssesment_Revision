<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\Program;
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
}
