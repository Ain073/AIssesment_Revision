<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
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

        return view('admin-dean.designations.index', $this->sharedData('designations') + [
            'scopedCollege' => $scopedCollege,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'departmentChairs' => $teachers
                ->filter(fn (User $teacher) => $teacher->hasRole('department_chair'))
                ->values(),
            'availableDepartmentChairTeachers' => $teachers
                ->reject(fn (User $teacher) => $teacher->hasRole('admin_dean') || $teacher->hasRole('department_chair'))
                ->reject(fn (User $teacher) => $chairDepartmentIds->contains($teacher->instructorProfile?->department_id))
                ->values(),
        ]);
    }

    public function grantDepartmentChair(User $user): RedirectResponse
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

        if ($this->departmentHasChair($departmentId, $teacher->id)) {
            return redirect()
                ->back()
                ->withErrors(new MessageBag(['designation' => 'This department already has a Department Chair. Remove the current designation first.']));
        }

        $roleId = Role::query()->where('role_name', 'department_chair')->value('role_id');

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

        Log::info('Department Chair designation granted by dean.', [
            'actor_id' => Auth::id(),
            'college_id' => $this->scopedCollege($actor)?->college_id,
            'user_id' => $teacher->id,
            'email' => $teacher->email,
        ]);

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

        Log::info('Department Chair designation removed by dean.', [
            'actor_id' => Auth::id(),
            'college_id' => $this->scopedCollege($actor)?->college_id,
            'user_id' => $teacher->id,
            'email' => $teacher->email,
        ]);

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
