<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CollegeDepartmentController extends Controller
{
    public function index(): View
    {
        return view('super-admin.colleges.index', [
            'colleges' => College::withCount('departments')
                ->orderBy('college_name')
                ->get(),
            'departments' => Department::with('college')
                ->withCount('instructorProfiles')
                ->orderBy('dept_name')
                ->get(),
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

    public function updateCollege(Request $request, College $college): RedirectResponse
    {
        $validated = $request->validate([
            'college_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('colleges', 'college_name')
                    ->ignore($college->getKey(), $college->getKeyName()),
            ],
        ]);

        $college->update($validated);

        Log::info('College updated by super admin.', [
            'actor_id' => Auth::id(),
            'college_id' => $college->college_id,
            'college_name' => $college->college_name,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'College updated successfully.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $request->input('college_id'))
                    ->ignore($department->getKey(), $department->getKeyName()),
            ],
        ]);

        $department->update($validated);

        Log::info('Department updated by super admin.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $department->dept_name,
            'college_id' => $department->college_id,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', 'Department updated successfully.');
    }

    public function destroyCollege(College $college): RedirectResponse
    {
        $collegeName = $college->college_name;
        $departmentCount = $college->departments()->count();
        $programCount = $college->programs()->count();

        try {
            $college->delete();
        } catch (Throwable $exception) {
            Log::warning('College delete failed.', [
                'actor_id' => Auth::id(),
                'college_id' => $college->college_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('super-admin.colleges')
                ->withErrors("Unable to delete {$collegeName} right now. Please try again.");
        }

        Log::info('College deleted by super admin.', [
            'actor_id' => Auth::id(),
            'college_id' => $college->college_id,
            'college_name' => $collegeName,
            'departments_removed' => $departmentCount,
            'programs_removed' => $programCount,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', "{$collegeName} deleted successfully.");
    }

    public function destroyDepartment(Department $department): RedirectResponse
    {
        $departmentName = $department->dept_name;
        $instructorCount = $department->instructorProfiles()->count();

        try {
            $department->delete();
        } catch (Throwable $exception) {
            Log::warning('Department delete failed.', [
                'actor_id' => Auth::id(),
                'department_id' => $department->department_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('super-admin.colleges')
                ->withErrors("Unable to delete {$departmentName} right now. Please try again.");
        }

        Log::info('Department deleted by super admin.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $departmentName,
            'instructor_profiles_affected' => $instructorCount,
        ]);

        return redirect()
            ->route('super-admin.colleges')
            ->with('status', "{$departmentName} deleted successfully.");
    }
}
