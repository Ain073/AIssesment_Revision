<?php

namespace App\Http\Controllers\DepartmentChair;

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

        return view(
            'department-chair.users.index',
            $this->sharedData($user, 'teachers') + $this->departmentUserDirectoryData($user, $request, $importer, $request->query('tab', 'students'))
        );
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

        if ($result['initial_password_emails_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some initial password emails were not delivered.');
        }

        return $redirect;
    }
}
