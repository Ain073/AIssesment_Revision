<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DesignationController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $teachers = $this->scopedTeachers();

        return view('admin-dean.designations.index', $this->sharedData('designations') + [
            'scopedCollege' => $scopedCollege,
            'departmentChairs' => $teachers
                ->filter(fn (User $teacher) => $teacher->hasRole('department_chair'))
                ->values(),
            'availableDepartmentChairTeachers' => $teachers
                ->reject(fn (User $teacher) => $teacher->hasRole('admin_dean') || $teacher->hasRole('department_chair'))
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
    private function scopedTeachers(): Collection
    {
        $scopedCollege = $this->scopedCollege($this->currentUser());

        if (! $scopedCollege) {
            return collect();
        }

        return User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->whereHas(
                'instructorProfile.department',
                fn ($query) => $query->where('college_id', $scopedCollege->college_id)
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
}
