<?php

namespace App\Http\Controllers\AdminDean;

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
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.programs.index', $this->sharedData('programs') + [
            'programs' => Program::with('college')
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->withCount('studentProfiles')
                ->orderBy('program_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('programs')])
                : collect(),
            'scopedCollege' => $scopedCollege,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scopedCollege = $this->scopedCollege($this->currentUser());
        $scopedCollegeId = $scopedCollege?->college_id;

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating programs.');

        $validated = $request->validate([
            'college_id' => ['nullable', 'integer', Rule::in([$scopedCollegeId])],
            'program_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'program_name')
                    ->where('college_id', $scopedCollegeId),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['college_id'] = $scopedCollegeId;
        $program = Program::create($validated);

        Log::info('Program created by admin/dean.', [
            'actor_id' => Auth::id(),
            'program_id' => $program->program_id,
            'program_name' => $program->program_name,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);

        return redirect()
            ->route('admin-dean.programs')
            ->with('status', 'Program added successfully.');
    }
}
