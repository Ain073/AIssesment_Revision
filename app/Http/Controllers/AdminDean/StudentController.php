<?php

namespace App\Http\Controllers\AdminDean;

use App\Models\Program;
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
        $scopedCollege = $this->scopedCollege($user);
        $scopedCollegeId = $scopedCollege?->college_id;
        $programs = Program::query()
            ->with('college')
            ->when($scopedCollegeId, fn ($query) => $query->where('college_id', $scopedCollegeId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('program_name')
            ->get();

        $students = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->whereHas('program', fn ($query) => $query->when($scopedCollegeId, fn ($inner) => $inner->where('college_id', $scopedCollegeId), fn ($inner) => $inner->whereRaw('1 = 0')))
            ->get()
            ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
            ->values();

        return view('admin-dean.students', $this->sharedData('students') + [
            'students' => $students,
            'programs' => $programs,
            'scopedCollege' => $scopedCollege,
            'studentImportPreview' => $importer->previewForRequest($request, $this->studentImportScope($scopedCollege)),
        ]);
    }

    public function downloadImportSample(StudentAccountImportService $importer): StreamedResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $program = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->first();

        abort_unless($program, 404, 'No program is available for student account import.');

        return $importer->sampleCsv();
    }

    public function previewImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $programs = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->get();

        abort_unless($college && $programs->isNotEmpty(), 403, 'A college and related program are required before importing students.');

        $token = $importer->previewUpload($request, $programs, $this->studentImportScope($college));

        return redirect()->route('admin-dean.students', ['import_token' => $token]);
    }

    public function confirmImport(Request $request, StudentAccountImportService $importer): RedirectResponse
    {
        $college = $this->scopedCollege($this->currentUser());
        $programs = Program::query()->where('college_id', $college?->college_id)->orderBy('program_name')->get();

        abort_unless($college && $programs->isNotEmpty(), 403);

        $result = $importer->confirm($request, $programs, $this->studentImportScope($college));
        $redirect = redirect()
            ->route('admin-dean.students')
            ->with('status', $result['created_count'].' student accounts created successfully.');

        if ($result['setup_links_sent'] < $result['created_count']) {
            $redirect->with('mail_warning', 'Some password setup emails were not delivered. Those students can request a new link through Forgot Password.');
        }

        return $redirect;
    }
}
