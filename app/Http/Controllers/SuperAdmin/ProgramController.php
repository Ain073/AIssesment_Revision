<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\College;
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
        $selectedCollegeId = $request->integer('college');

        if ($selectedCollegeId && ! College::query()->whereKey($selectedCollegeId)->exists()) {
            $selectedCollegeId = null;
        }

        return view('super-admin.programs', [
            'colleges' => College::query()
                ->orderBy('college_name')
                ->get(),
            'programs' => $this->programsList($selectedCollegeId),
            'selectedCollegeId' => $selectedCollegeId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $request->input('college_id')),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $program = Program::create($validated);

        Log::info('Program created by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('super-admin.programs')
            ->with('status', 'Program added successfully.');
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,college_id'],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $request->input('college_id'))
                    ->ignore($program->program_id, 'program_id'),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $program->update($validated);

        Log::info('Program updated by super admin.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('super-admin.programs', ['college' => $program->college_id])
            ->with('status', 'Program updated successfully.');
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        $program->loadCount(['studentProfiles', 'subjectPrograms']);
        $redirectCollegeId = $request->integer('college') ?: $program->college_id;

        if ($program->student_profiles_count > 0 || $program->subject_programs_count > 0) {
            return redirect()
                ->route('super-admin.programs', ['college' => $redirectCollegeId])
                ->withErrors('This program still has linked students or subject mappings. Remove those links before deleting it.');
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
            ->route('super-admin.programs', ['college' => $redirectCollegeId])
            ->with('status', "{$programName} deleted successfully.");
    }

    private function programsList(?int $collegeId = null)
    {
        return Program::with('college')
            ->withCount(['studentProfiles', 'subjectPrograms'])
            ->when($collegeId, fn ($query) => $query->where('college_id', $collegeId))
            ->orderBy('program_name')
            ->get();
    }
}
