<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollegeController extends Controller
{
    public function dashboard(): View
    {
        return view('super-admin.dashboard', [
            'totalColleges' => College::count(),
            'totalDepartments' => Department::count(),
            'totalAdminDeans' => $this->countUsersByRole('admin_dean'),
            'totalDepartmentChairs' => $this->countUsersByRole('department_chair'),
        ]);
    }

    public function index(): View
    {
        return view('super-admin.colleges', [
            'colleges' => College::withCount('departments')
                ->orderBy('college_name')
                ->get(),
            'departments' => Department::with('college')
                ->orderBy('dept_name')
                ->get(),
            'totalColleges' => College::count(),
            'totalDepartments' => Department::count(),
        ]);
    }

    public function storeCollege(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_name' => ['required', 'string', 'max:255', 'unique:colleges,college_name'],
        ]);

        $college = College::create($validated);

        Log::info('College created by super admin.', [
            'actor_id' => Auth::id(),
            'college_id' => $college->college_id,
            'college_name' => $college->college_name,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'College added successfully.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $request->input('college_id')),
            ],
        ]);

        $department = Department::create($validated);

        Log::info('Department created by super admin.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $department->dept_name,
            'college_id' => $department->college_id,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'Department added successfully.');
    }

    private function countUsersByRole(string $roleName): int
    {
        return DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('roles.role_name', $roleName)
            ->count();
    }
}
