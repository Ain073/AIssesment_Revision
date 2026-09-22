<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $programs = $this->programs($department?->department_id);

        return view('department-chair.programs.index', $this->sharedData($user, 'programs') + [
            'scopedDepartment' => $department,
            'programs' => $programs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $department = $this->scopedDepartment($this->currentUser());

        abort_unless($department, 403, 'Department assignment is required before creating programs.');

        $validated = $request->validate([
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('department_id', $department->department_id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $program = Program::query()->create($validated + [
            'department_id' => $department->department_id,
        ]);

        Log::info('Program created by department chair.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'department_id' => $department->department_id,
            'program_name' => $program->program_name,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('department-chair.programs')
            ->with('status', 'Program added successfully.');
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $department = $this->scopedDepartment($this->currentUser());
        $program = $this->scopedProgram($program, $department?->department_id);

        $validated = $request->validate([
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('department_id', $department->department_id)
                    ->ignore($program->program_id, 'program_id'),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $program->update($validated);

        Log::info('Program updated by department chair.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'department_id' => $department->department_id,
            'program_name' => $program->program_name,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('department-chair.programs')
            ->with('status', 'Program updated successfully.');
    }

    public function destroy(Program $program): RedirectResponse
    {
        $department = $this->scopedDepartment($this->currentUser());
        $program = $this->scopedProgram($program, $department?->department_id);
        $program->loadCount(['studentProfiles', 'classes']);

        if ($program->student_profiles_count > 0 || $program->classes_count > 0) {
            return redirect()
                ->route('department-chair.programs')
                ->withErrors('This program still has linked students or classes. Remove those links before deleting it.');
        }

        $programName = $program->program_name;
        $programId = $program->program_id;

        $program->delete();

        Log::warning('Program deleted by department chair.', [
            'actor_id' => Auth::id(),
            'program_id' => $programId,
            'department_id' => $department?->department_id,
            'program_name' => $programName,
        ]);

        return redirect()
            ->route('department-chair.programs')
            ->with('status', "{$programName} deleted successfully.");
    }

    private function programs(?int $departmentId)
    {
        return $departmentId
            ? Program::query()
                ->with('department.college')
                ->withCount(['studentProfiles', 'classes'])
                ->where('department_id', $departmentId)
                ->orderBy('program_name')
                ->get()
            : collect();
    }

    private function scopedProgram(Program $program, ?int $departmentId): Program
    {
        abort_unless(
            $departmentId && (int) $program->department_id === (int) $departmentId,
            403,
            'You are not allowed to manage this program.'
        );

        return $program;
    }
}
