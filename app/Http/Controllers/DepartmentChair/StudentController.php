<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Models\StudentProfile;
use App\Services\StudentAccountImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends BaseController
{
    public function index(Request $request, StudentAccountImportService $importer): View
    {
        $user = $this->currentUser();
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $scopedProgramIds = $scopedPrograms->pluck('program_id');
        $students = $scopedProgramIds->isNotEmpty()
            ? StudentProfile::query()
                ->with(['user.roles', 'program.college'])
                ->whereIn('program_id', $scopedProgramIds)
                ->get()
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values()
            : collect();

        return view('department-chair.students', $this->sharedData($user, 'students') + [
            'students' => $students,
            'scopedDepartment' => $scopedDepartment,
            'scopedPrograms' => $scopedPrograms,
            'totalStudents' => $students->count(),
            'activeStudents' => $students->filter(fn (StudentProfile $student) => $student->user?->status === 'active')->count(),
            'programCount' => $scopedPrograms->count(),
            'studentImportPreview' => $importer->previewForRequest($request, $this->studentImportScope($scopedDepartment)),
        ]);
    }

    public function downloadImportSample(StudentAccountImportService $importer): StreamedResponse
    {
        $user = $this->currentUser();
        $program = $this->scopedPrograms($this->scopedDepartment($user))->first();

        abort_unless($program, 404, 'No program is available for student account import.');

        return $importer->sampleCsv();
    }

    public function previewImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $programs = $this->scopedPrograms($department);

        abort_unless($department && $programs->isNotEmpty(), 403, 'A department and related program are required before importing students.');

        $token = $importer->previewUpload($request, $programs, $this->studentImportScope($department));

        return redirect()->route('department-chair.students', ['import_token' => $token]);
    }

    public function confirmImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $user = $this->currentUser();
        $department = $this->scopedDepartment($user);
        $programs = $this->scopedPrograms($department);

        abort_unless($department && $programs->isNotEmpty(), 403);

        $result = $importer->confirm($request, $programs, $this->studentImportScope($department));

        $redirect = redirect()
            ->route('department-chair.students')
            ->with('status', $result['created_count'].' student accounts created successfully.');

        if ($result['setup_links_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some password setup emails were not delivered. Those students can request a new link through Forgot Password.');
        }

        return $redirect;
    }
}
