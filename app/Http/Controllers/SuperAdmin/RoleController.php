<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
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
        'department_chair',
    ];

    public function index(): View
    {
        $teachers = User::with('roles')
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
        $departmentChairs = $teachers->filter->hasRole('department_chair')->values();

        return view('super-admin.roles', [
            'teachers' => $teachers,
            'adminDeans' => $adminDeans,
            'departmentChairs' => $departmentChairs,
            'availableAdminDeanTeachers' => $teachers->reject(fn (User $user) => $user->hasRole('admin_dean'))->values(),
            'availableDepartmentChairTeachers' => $teachers->reject(fn (User $user) => $user->hasRole('department_chair'))->values(),
            'totalTeachers' => $teachers->count(),
            'totalAdminDeans' => $adminDeans->count(),
            'totalDepartmentChairs' => $departmentChairs->count(),
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_name' => ['required', Rule::in(self::MANAGED_ROLES)],
            'form_mode' => ['nullable', 'string'],
        ]);

        $user = User::with('roles')->findOrFail($validated['user_id']);

        if (! $user->hasRole('instructor') || $user->hasRole('super_admin')) {
            return redirect()
                ->route('super-admin.roles')
                ->withErrors(new MessageBag(['role' => 'Only teacher accounts can receive this authorization.']));
        }

        $roleId = Role::where('role_name', $validated['role_name'])->value('role_id');

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

        Log::info('Authorization granted by super admin.', [
            'actor_id' => Auth::id(),
            'user_id' => $user->id,
            'email' => $user->email,
            'role_name' => $validated['role_name'],
        ]);

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Authorization granted successfully.');
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

        Log::info('Authorization removed by super admin.', [
            'actor_id' => Auth::id(),
            'user_id' => (int) $validated['user_id'],
            'role_name' => $validated['role_name'],
        ]);

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Authorization removed successfully.');
    }
}
