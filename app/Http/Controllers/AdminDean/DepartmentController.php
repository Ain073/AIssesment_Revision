<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends BaseController
{
    public function index(): View
    {
        $user = $this->currentUser();
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;

        return view('admin-dean.departments.index', $this->sharedData('departments') + [
            'departments' => Department::with('college')
                ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
                ->withCount('instructorProfiles')
                ->orderBy('dept_name')
                ->get(),
            'colleges' => $scopedCollege
                ? collect([$scopedCollege->loadCount('departments')])
                : collect(),
            'scopedCollege' => $scopedCollege,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scopedCollege = $this->scopedCollege($this->currentUser());
        $scopedCollegeId = $scopedCollege?->college_id;

        abort_unless($scopedCollege, 403, 'Admin/Dean account needs an assigned college before creating departments.');

        $validated = $request->validate([
            'college_id' => ['nullable', 'integer', Rule::in([$scopedCollegeId])],
            'dept_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'dept_name')
                    ->where('college_id', $scopedCollegeId),
            ],
        ]);

        $validated['college_id'] = $scopedCollegeId;
        $department = Department::create($validated);

        Log::info('Department created by admin/dean.', [
            'actor_id' => Auth::id(),
            'department_id' => $department->department_id,
            'department_name' => $department->dept_name,
            'college_id' => $department->college_id,
        ]);

        return redirect()
            ->route('admin-dean.departments')
            ->with('status', 'Department added successfully.');
    }
}
