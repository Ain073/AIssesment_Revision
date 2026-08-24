<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

class DesignationController extends BaseController
{
    public function grantDepartmentChair(User $user): RedirectResponse
    {
        $actor = $this->currentUser();
        $teacher = $this->scopedTeacher($user);

        if ($teacher->hasRole('admin_dean')) {
            return redirect()
                ->route('admin-dean.teachers')
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
            ->route('admin-dean.teachers')
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
            ->route('admin-dean.teachers')
            ->with('status', 'Department Chair designation removed successfully.');
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
