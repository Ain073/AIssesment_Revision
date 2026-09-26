<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\DesignationDetail;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    private const MANAGED_ROLES = [
        'admin_dean',
    ];

    public function index(): View
    {
        $colleges = College::with('departments')->orderBy('college_name')->get();

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', function ($query) {
                $query->where('role_name', 'instructor');
            })
            ->whereDoesntHave('roles', function ($query) {
                $query->where('role_name', 'super_admin');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        $adminDeans = $teachers->filter->hasRole('admin_dean')->values();

        $designationHistory = DesignationDetail::with(['instructor.user', 'college', 'department', 'designatedByUser'])
            ->where('designation_id', 1) // Dean
            ->orderByDesc('created_at')
            ->get();

        return view('super-admin.roles.index', [
            'colleges' => $colleges,
            'teachers' => $teachers,
            'adminDeans' => $adminDeans,
            'availableAdminDeanTeachers' => $teachers
                ->reject(fn (User $user) => $user->hasRole('admin_dean'))
                ->filter(fn (User $user) => (bool) $user->instructorProfile?->department?->college_id)
                ->values(),
            'designationHistory' => $designationHistory,
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_name' => ['required', Rule::in(self::MANAGED_ROLES)],
            'form_mode' => ['nullable', 'string'],
        ]);

        $user = User::with(['roles', 'instructorProfile.department.college'])->findOrFail($validated['user_id']);

        if (! $user->hasRole('instructor') || $user->hasRole('super_admin')) {
            return redirect()
                ->route('super-admin.roles')
                ->withErrors(new MessageBag(['role' => 'Only teacher accounts can receive this designation.']));
        }

        $collegeId = $user->instructorProfile?->department?->college_id;

        if (! $collegeId) {
            return redirect()
                ->route('super-admin.roles')
                ->withErrors(new MessageBag(['role' => 'Teacher must have an assigned college before receiving Dean designation.']));
        }

        // If the teacher was a Department Chair, cleanly transition them to Dean
        if ($user->hasRole('department_chair')) {
            $chairRoleId = Role::where('role_name', 'department_chair')->value('role_id');
            if ($chairRoleId) {
                DB::table('user_roles')->where('user_id', $user->id)->where('role_id', $chairRoleId)->delete();
            }
            if ($user->instructorProfile) {
                DesignationDetail::query()
                    ->where('instructor_id', $user->instructorProfile->instructor_profile_id)
                    ->where('designation_id', 2) // Department Chair
                    ->where('status', 'active')
                    ->update([
                        'end_date' => now()->toDateString(),
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);
            }
        }

        $roleId = Role::where('role_name', $validated['role_name'])->value('role_id');

        // Conclude any prior active Dean appointment for this college
        $previousDeans = User::query()
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'admin_dean'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->where('college_id', $collegeId)
            )
            ->get();

        foreach ($previousDeans as $prevDean) {
            DB::table('user_roles')
                ->where('user_id', $prevDean->id)
                ->where('role_id', $roleId)
                ->delete();

            if ($prevDean->instructorProfile) {
                DesignationDetail::query()
                    ->where('instructor_id', $prevDean->instructorProfile->instructor_profile_id)
                    ->where('designation_id', 1) // Dean
                    ->where('status', 'active')
                    ->update([
                        'end_date' => now()->toDateString(),
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('user_roles')->updateOrInsert(
            [
                'user_id' => $user->id,
                'role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Record appointment in designation_details
        $currentYear = (int) date('Y');
        $defaultAcademicYear = date('n') >= 8 ? "{$currentYear}-" . ($currentYear + 1) : ($currentYear - 1) . "-{$currentYear}";
        $academicYear = $request->input('academic_year') ?: $defaultAcademicYear;
        $effectivityDate = $request->input('effectivity_date') ?: now()->toDateString();
        $remarks = $request->input('remarks') ?: 'Designated as College Dean by Admin';

        if ($user->instructorProfile) {
            DesignationDetail::create([
                'instructor_id' => $user->instructorProfile->instructor_profile_id,
                'designation_id' => 1, // Dean
                'college_id' => $collegeId,
                'department_id' => $user->instructorProfile->department_id,
                'academic_year' => $academicYear,
                'effectivity_date' => $effectivityDate,
                'end_date' => null,
                'status' => 'active',
                'designated_by' => Auth::id(),
                'remarks' => $remarks,
            ]);
        }

        Log::info('Designation granted by admin.', [
            'actor_id' => Auth::id(),
            'user_id' => $user->id,
            'email' => $user->email,
            'role_name' => $validated['role_name'],
        ]);

        $collegeName = $user->instructorProfile?->department?->college?->college_name ?? 'Unknown College';
        AuditLogger::log('DESIGNATE', 'Designations', "Designated {$user->displayName()} as Dean in {$collegeName}", $user);

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Dean designation granted successfully.');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_name' => ['required', Rule::in(self::MANAGED_ROLES)],
        ]);

        $roleId = Role::where('role_name', $validated['role_name'])->value('role_id');

        DB::table('user_roles')
            ->where('user_id', $validated['user_id'])
            ->where('role_id', $roleId)
            ->delete();

        // Mark active Dean designation as concluded with end_date
        $user = User::with('instructorProfile')->find($validated['user_id']);
        if ($user?->instructorProfile) {
            DesignationDetail::query()
                ->where('instructor_id', $user->instructorProfile->instructor_profile_id)
                ->where('designation_id', 1)
                ->where('status', 'active')
                ->update([
                    'end_date' => now()->toDateString(),
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
        }

        Log::info('Designation removed by admin.', [
            'actor_id' => Auth::id(),
            'user_id' => (int) $validated['user_id'],
            'role_name' => $validated['role_name'],
        ]);

        AuditLogger::log('REVOKE', 'Designations', "Revoked Dean designation from {$user?->displayName()}");

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Designation removed successfully.');
    }

    private function collegeHasDean(int $collegeId, int $exceptUserId): bool
    {
        return User::query()
            ->whereKeyNot($exceptUserId)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'admin_dean'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->where('college_id', $collegeId)
            )
            ->exists();
    }
}
