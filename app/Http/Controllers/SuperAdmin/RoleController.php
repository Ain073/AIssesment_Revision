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
        return view('super-admin.roles.index', [
            'teachers' => $teachers,
            'adminDeans' => $adminDeans,
            'availableAdminDeanTeachers' => $teachers
                ->reject(fn (User $user) => $user->hasRole('admin_dean') || $user->hasRole('department_chair'))
                ->values(),
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
                ->withErrors(new MessageBag(['role' => 'Only teacher accounts can receive this designation.']));
        }

        if ($user->hasRole('department_chair')) {
            return redirect()
                ->route('super-admin.roles')
                ->withErrors(new MessageBag([
                    'role' => 'A teacher can only have one designation. Ask the Dean to remove the current Department Chair designation first.',
                ]));
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

        Log::info('Designation granted by admin.', [
            'actor_id' => Auth::id(),
            'user_id' => $user->id,
            'email' => $user->email,
            'role_name' => $validated['role_name'],
        ]);

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Designation granted successfully.');
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

        Log::info('Designation removed by admin.', [
            'actor_id' => Auth::id(),
            'user_id' => (int) $validated['user_id'],
            'role_name' => $validated['role_name'],
        ]);

        return redirect()
            ->route('super-admin.roles')
            ->with('status', 'Designation removed successfully.');
    }
}
