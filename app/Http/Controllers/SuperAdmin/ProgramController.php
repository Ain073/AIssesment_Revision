<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(Request $request): View
    {
        $selectedDepartment = Department::query()
            ->where('public_id', $request->query('department'))
            ->first();
        $selectedDepartmentId = $selectedDepartment?->department_id;

        return view('super-admin.programs.index', [
            'departments' => Department::with('college')
                ->orderBy('dept_name')
                ->get(),
            'programs' => $this->programsList($selectedDepartmentId),
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDepartmentKey' => $selectedDepartment?->public_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,department_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('department_id', $request->input('department_id')),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $department = Department::query()->findOrFail($validated['department_id']);
        $validated['college_id'] = $department->college_id;
        $program = Program::create($validated);

        Log::info('Program created by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'department_id' => $program->department_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('super-admin.programs', ['department' => $department->public_id])
            ->with('status', 'Program added successfully.');
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,department_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('department_id', $request->input('department_id'))
                    ->ignore($program->program_id, 'program_id'),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $department = Department::query()->findOrFail($validated['department_id']);
        $validated['college_id'] = $department->college_id;
        $program->update($validated);

        Log::info('Program updated by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'department_id' => $program->department_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('super-admin.programs', ['department' => $program->department?->public_id])
            ->with('status', 'Program updated successfully.');
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        $program->loadMissing('department.college');
        $program->loadCount(['studentProfiles', 'subjects']);
        $selectedDepartment = Department::query()
            ->where('public_id', $request->query('department'))
            ->first();
        $redirectDepartmentKey = $selectedDepartment?->public_id ?? $program->department?->public_id;

        if ($program->student_profiles_count > 0 || $program->subjects_count > 0) {
            return redirect()
                ->route('super-admin.programs', ['department' => $redirectDepartmentKey])
                ->withErrors('This program still has linked students or subjects. Remove those links before deleting it.');
        }

        $programName = $program->program_name;
        $programId = $program->program_id;

        $program->delete();

        Log::warning('Program deleted by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $programId,
            'program_name' => $programName,
        ]);

        return redirect()
            ->route('super-admin.programs', ['department' => $redirectDepartmentKey])
            ->with('status', "{$programName} deleted successfully.");
    }

    private function programsList(?int $departmentId = null)
    {
        return Program::with(['department.college'])
            ->withCount(['studentProfiles', 'subjects'])
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('program_name')
            ->get();
    }
}
