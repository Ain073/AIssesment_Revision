<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
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
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DesignationController extends BaseController
{
    public function index(Request $request): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $departments = $scopedCollege
            ? Department::query()
                ->where('college_id', $scopedCollege->college_id)
                ->orderBy('dept_name')
                ->get()
            : collect();
        $selectedDepartmentId = $request->integer('department_id') ?: null;

        if ($selectedDepartmentId && ! $departments->contains('department_id', $selectedDepartmentId)) {
            $selectedDepartmentId = null;
        }

        $allScopedTeachers = $this->scopedTeachers();
        $chairDepartmentIds = $allScopedTeachers
            ->filter(fn (User $teacher) => $teacher->hasRole('department_chair'))
            ->pluck('instructorProfile.department_id')
            ->filter()
            ->unique()
            ->values();
        $teachers = $this->scopedTeachers($selectedDepartmentId);

        $designationHistory = DesignationDetail::with(['instructor.user', 'department', 'designatedByUser'])
            ->where('designation_id', 2) // Department Chair
            ->when($scopedCollege, fn ($query) => $query->where('college_id', $scopedCollege->college_id))
            ->orderByDesc('created_at')
            ->get();

        return view('admin-dean.designations.index', $this->sharedData('designations') + [
            'scopedCollege' => $scopedCollege,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'departmentChairs' => $teachers
                ->filter(fn (User $teacher) => $teacher->hasRole('department_chair'))
                ->values(),
            'availableDepartmentChairTeachers' => $teachers
                ->reject(fn (User $teacher) => $teacher->hasRole('admin_dean') || $teacher->hasRole('department_chair'))
                ->values(),
            'designationHistory' => $designationHistory,
        ]);
    }

    public function grantDepartmentChair(Request $request, User $user): RedirectResponse
    {
        $actor = $this->currentUser();
        $teacher = $this->scopedTeacher($user);

        if ($teacher->hasRole('admin_dean')) {
            return redirect()
                ->back()
                ->withErrors(new MessageBag(['designation' => 'Dean accounts cannot also be designated as Department Chair.']));
        }

        $departmentId = $teacher->instructorProfile?->department_id;

        if (! $departmentId) {
            return redirect()
                ->back()
                ->withErrors(new MessageBag(['designation' => 'Teacher must have an assigned department before receiving Department Chair designation.']));
        }

        $roleId = Role::query()->where('role_name', 'department_chair')->value('role_id');

        // Conclude any prior active Department Chair appointment for this department
        $priorChairs = User::query()
            ->whereKeyNot($teacher->id)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
            ->whereHas('instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->get();

        foreach ($priorChairs as $priorChair) {
            DB::table('user_roles')->where('user_id', $priorChair->id)->where('role_id', $roleId)->delete();
        }

        DesignationDetail::query()
            ->where('department_id', $departmentId)
            ->where('designation_id', 2)
            ->where('status', 'active')
            ->update([
                'end_date' => now()->toDateString(),
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        DB::table('user_roles')->updateOrInsert(
            [
                'user_id' => $teacher->id,
                'role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Record the new appointment in designation_details
        $currentYear = (int) date('Y');
        $defaultAy = date('n') >= 8 ? "{$currentYear}-" . ($currentYear + 1) : ($currentYear - 1) . "-{$currentYear}";
        $academicYear = $request->input('academic_year') ?: $defaultAy;
        $effectivityDate = $request->input('effectivity_date') ?: now()->toDateString();
        $remarks = $request->input('remarks') ?: 'Designated as Department Chair by College Dean';

        if ($teacher->instructorProfile) {
            DesignationDetail::create([
                'instructor_id' => $teacher->instructorProfile->instructor_profile_id,
                'designation_id' => 2, // Department Chair
                'department_id' => $departmentId,
                'college_id' => $this->scopedCollege($actor)?->college_id,
                'academic_year' => $academicYear,
                'effectivity_date' => $effectivityDate,
                'end_date' => null,
                'status' => 'active',
                'designated_by' => Auth::id(),
                'remarks' => $remarks,
            ]);
        }

        Log::info('Department Chair designation granted by dean.', [
            'actor_id' => Auth::id(),
            'college_id' => $this->scopedCollege($actor)?->college_id,
            'user_id' => $teacher->id,
            'email' => $teacher->email,
        ]);

        $deptName = $teacher->instructorProfile?->department?->dept_name ?? 'Unknown Dept';
        AuditLogger::log('DESIGNATE', 'Designations', "Designated {$teacher->displayName()} as Department Chair of {$deptName}", $teacher);

        return redirect()
            ->back()
            ->with('status', 'Department Chair designation granted successfully.');
    }

    public function revokeDepartmentChair(User $user): RedirectResponse
    {
        $actor = $this->currentUser();
        $teacher = $this->scopedTeacher($user);
        $roleId = Role::query()->where('role_name', 'department_chair')->value('role_id');

        DB::table('user_roles')
            ->where('user_id', $teacher->id)
            ->where('role_id', $roleId)
            ->delete();

        // Mark active designation as concluded with end_date
        if ($teacher->instructorProfile) {
            DesignationDetail::query()
                ->where('instructor_id', $teacher->instructorProfile->instructor_profile_id)
                ->where('designation_id', 2)
                ->where('status', 'active')
                ->update([
                    'end_date' => now()->toDateString(),
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
        }

        Log::info('Department Chair designation removed by dean.', [
            'actor_id' => Auth::id(),
            'college_id' => $this->scopedCollege($actor)?->college_id,
            'user_id' => $teacher->id,
            'email' => $teacher->email,
        ]);

        AuditLogger::log('REVOKE', 'Designations', "Revoked Department Chair designation from {$teacher->displayName()}");

        return redirect()
            ->back()
            ->with('status', 'Department Chair designation removed successfully.');
    }

    /**
     * @return Collection<int, User>
     */
    private function scopedTeachers(?int $departmentId = null): Collection
    {
        $scopedCollege = $this->scopedCollege($this->currentUser());

        if (! $scopedCollege) {
            return collect();
        }

        return User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query
                    ->where('college_id', $scopedCollege->college_id)
                    ->when($departmentId, fn ($departmentQuery) => $departmentQuery->where('department_id', $departmentId))
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();
    }

    private function scopedTeacher(User $user): User
    {
        $actor = $this->currentUser();
        $scopedCollege = $this->scopedCollege($actor);

        abort_unless($scopedCollege, 403, 'Dean account needs an assigned college before managing designations.');

        $user->loadMissing(['roles', 'instructorProfile.department']);

        $isScopedTeacher = $user->hasRole('instructor')
            && (int) $user->instructorProfile?->department?->college_id === (int) $scopedCollege->college_id;

        abort_unless($isScopedTeacher, 403);

        return $user;
    }

    private function departmentHasChair(int $departmentId, int $exceptUserId): bool
    {
        return User::query()
            ->whereKeyNot($exceptUserId)
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'department_chair'))
            ->whereHas('instructorProfile', fn ($query) => $query->where('department_id', $departmentId))
            ->exists();
    }
}
