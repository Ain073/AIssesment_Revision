<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\InstructorProfile;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleXMLElement;
use ZipArchive;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $classesCount = $instructorProfile?->classes()->count() ?? 0;

        return view('instructor.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Classes Handled',
                    'value' => $classesCount,
                    'caption' => 'Classes currently linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Students',
                    'value' => 0,
                    'caption' => 'Across your active classes',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Assessments',
                    'value' => 0,
                    'caption' => 'Drafts and published items',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Pending Checks',
                    'value' => 0,
                    'caption' => 'Submissions waiting for review',
                    'icon' => 'fact_check',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'View Classes',
                    'description' => 'Open your subject and section list.',
                    'href' => route('instructor.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Open Assessments',
                    'description' => 'Manage quizzes, exams, and activities.',
                    'href' => route('instructor.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Browse Students',
                    'description' => 'Review student rosters by class.',
                    'href' => route('instructor.students'),
                    'icon' => 'groups',
                ],
            ],
            'pendingWork' => [
                [
                    'title' => 'No classes linked yet',
                    'description' => 'Once classes are assigned to this instructor, the next teaching tasks will appear here.',
                    'state' => 'Waiting for setup',
                ],
                [
                    'title' => 'No assessments in draft',
                    'description' => 'Draft assessments will be listed here for quick continuation.',
                    'state' => 'Ready',
                ],
                [
                    'title' => 'No submissions to check',
                    'description' => 'Review tasks will surface here once student work starts coming in.',
                    'state' => 'Clear',
                ],
            ],
        ]);
    }

    public function classes(): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $classes = $instructorProfile
            ? $instructorProfile->classes()
                ->with('subject')
                ->withCount('students')
                ->latest('class_id')
                ->get()
            : collect();

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();

        return view('instructor.classes', $this->sharedData($user, 'classes') + [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'subjects' => $subjects,
            'totalClasses' => $classes->count(),
            'latestSchoolYear' => $classes->pluck('school_year')->filter()->unique()->first(),
        ]);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        abort_unless($instructorProfile, 403, 'Instructor profile is required before creating classes.');

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,subject_id'],
            'class_name' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
        ]);

        $class = $instructorProfile->classes()->create($validated);

        Log::info('Class created by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $class->class_id,
            'instructor_profile_id' => $instructorProfile->instructor_profile_id,
            'subject_id' => $class->subject_id,
            'class_name' => $class->class_name,
            'school_year' => $class->school_year,
        ]);

        return redirect()
            ->route('instructor.classes')
            ->with('status', 'Class added successfully.');
    }

    public function showClass(Request $request, AcademicClass $class): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);
        $activeTab = in_array($request->query('tab'), ['overview', 'students', 'assessments'], true)
            ? $request->query('tab')
            : 'students';

        $ownedClass->load([
            'subject',
            'instructorProfile.department.college',
            'students.user.roles',
            'students.program.college',
        ])->loadCount('students');

        return view('instructor.class-show', $this->sharedData($user, 'classes') + [
            'class' => $ownedClass,
            'activeTab' => $activeTab,
            'classTabs' => $this->classTabs($ownedClass, $activeTab),
            'enrolledStudents' => $ownedClass->students
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values(),
            'assessmentsTakenCount' => 0,
            'importPreview' => $this->pullImportPreview($request, $ownedClass),
        ]);
    }

    public function storeClassStudent(Request $request, AcademicClass $class): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:255'],
        ]);

        $studentNumber = trim($validated['student_number']);

        $studentProfile = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->where('student_number', $studentNumber)
            ->first();

        if (! $studentProfile || ! $studentProfile->user || ! $studentProfile->user->hasRole('student')) {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'No student account was found for that student number.'])
                ->withInput();
        }

        if ($studentProfile->user->status !== 'active') {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'This student account is inactive and cannot be added to a class yet.'])
                ->withInput();
        }

        if ($ownedClass->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_number' => 'This student is already enrolled in the selected class.'])
                ->withInput();
        }

        $ownedClass->students()->attach($studentProfile->student_profile_id);

        Log::info('Student enrolled into class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'student_number' => $studentProfile->student_number,
        ]);

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student added to class successfully.');
    }

    public function destroyClassStudent(AcademicClass $class, StudentProfile $studentProfile): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        if (! $ownedClass->students()->where('student_profiles.student_profile_id', $studentProfile->student_profile_id)->exists()) {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_file' => 'That student is not currently enrolled in this class.']);
        }

        $ownedClass->students()->detach($studentProfile->student_profile_id);

        Log::warning('Student removed from class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'student_profile_id' => $studentProfile->student_profile_id,
            'student_number' => $studentProfile->student_number,
        ]);

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', 'Student removed from class successfully.');
    }

    public function previewClassStudentsImport(Request $request, AcademicClass $class): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $validated = $request->validate([
            'student_file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,txt,xlsx',
            ],
        ]);

        $numbers = $this->extractStudentNumbersFromImport(
            $validated['student_file']->getRealPath(),
            Str::lower((string) $validated['student_file']->getClientOriginalExtension())
        );

        if (empty($numbers)) {
            throw ValidationException::withMessages([
                'student_file' => 'No student numbers were found in the uploaded file.',
            ]);
        }

        if (count($numbers) > 200) {
            throw ValidationException::withMessages([
                'student_file' => 'Please limit each import to 200 student numbers or fewer.',
            ]);
        }

        $importPreview = $this->buildImportPreview($ownedClass, $numbers);
        $importToken = (string) Str::uuid();

        $request->session()->put("class_student_imports.{$importToken}", [
            'class_id' => $ownedClass->class_id,
            'student_profile_ids' => $importPreview['ready_student_profile_ids'],
            'rows' => $importPreview['rows'],
            'summary' => $importPreview['summary'],
        ]);

        return redirect()
            ->route('instructor.classes.show', [
                'class' => $ownedClass,
                'tab' => 'students',
                'import_token' => $importToken,
            ])
            ->with('status', 'File processed. Review the preview before confirming the import.');
    }

    public function confirmClassStudentsImport(Request $request, AcademicClass $class): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $sessionKey = "class_student_imports.{$validated['import_token']}";
        $storedImport = $request->session()->get($sessionKey);

        if (! $storedImport || ($storedImport['class_id'] ?? null) !== $ownedClass->class_id) {
            return redirect()
                ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
                ->withErrors(['student_file' => 'The import preview has expired. Please upload the file again.']);
        }

        $studentProfileIds = collect($storedImport['student_profile_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $alreadyEnrolledIds = $ownedClass->students()
            ->whereIn('student_profiles.student_profile_id', $studentProfileIds)
            ->pluck('student_profiles.student_profile_id');

        $attachIds = $studentProfileIds
            ->diff($alreadyEnrolledIds)
            ->values();

        if ($attachIds->isNotEmpty()) {
            $ownedClass->students()->attach($attachIds->all());
        }

        $request->session()->forget($sessionKey);

        Log::info('Students imported into class by instructor.', [
            'actor_id' => $user->id,
            'class_id' => $ownedClass->class_id,
            'imported_count' => $attachIds->count(),
            'student_profile_ids' => $attachIds->all(),
        ]);

        return redirect()
            ->route('instructor.classes.show', ['class' => $ownedClass, 'tab' => 'students'])
            ->with('status', $attachIds->count() . ' student' . ($attachIds->count() === 1 ? '' : 's') . ' imported successfully.');
    }

    public function downloadClassStudentsImportSample(AcademicClass $class): Response
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClass = $this->ownedClass($class, $instructorProfile);

        $fileName = Str::slug($ownedClass->class_name ?: 'class') . '-student-import-sample.csv';
        $content = implode("\n", [
            'student_number',
            '2024-00001',
            '2024-00002',
            '2024-00003',
        ]) . "\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function assessments(): View
    {
        return view('instructor.assessments', $this->placeholderPageData(
            'assessments',
            'Assessments',
            'This is where we will build quiz, exam, and activity management for instructors.',
            [
                'Create and edit assessments',
                'Publish and monitor availability',
                'Track completion status by class',
            ],
        ));
    }

    public function students(): View
    {
        return view('instructor.students', $this->placeholderPageData(
            'students',
            'Students',
            'Student lists per class or section will live here once we connect enrollment data.',
            [
                'Roster per subject or section',
                'Student status and participation view',
                'Shortcuts to submissions and results',
            ],
        ));
    }

    private function placeholderPageData(
        string $activeNav,
        string $pageHeading,
        string $pageDescription,
        array $checklist
    ): array {
        $user = $this->currentUser();

        return $this->sharedData($user, $activeNav) + [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'pageChecklist' => $checklist,
        ];
    }

    private function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Instructor Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Instructor Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => $this->viewSwitches($user, 'instructor'),
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search records...',
        ];
    }

    private function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('instructor.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments')],
            ['key' => 'students', 'label' => 'Students', 'icon' => 'groups', 'href' => route('instructor.students')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    private function viewSwitches(User $user, string $activeMode): array
    {
        $switches = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Instructor',
                'icon' => 'co_present',
                'href' => route('instructor.dashboard'),
                'active' => $activeMode === 'instructor',
            ];
        }

        if ($user->hasRole('admin_dean')) {
            $switches[] = [
                'label' => 'Admin/Dean',
                'icon' => 'supervisor_account',
                'href' => route('admin-dean.dashboard'),
                'active' => $activeMode === 'admin_dean',
            ];
        }

        if ($user->hasRole('department_chair')) {
            $switches[] = [
                'label' => 'Dept Chair',
                'icon' => 'assignment_ind',
                'href' => route('department-chair.dashboard'),
                'active' => $activeMode === 'department_chair',
            ];
        }

        return $switches;
    }

    private function classTabs(AcademicClass $class, string $activeTab): array
    {
        return [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'overview']),
                'active' => $activeTab === 'overview',
            ],
            [
                'key' => 'students',
                'label' => 'Students',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'students']),
                'active' => $activeTab === 'students',
            ],
            [
                'key' => 'assessments',
                'label' => 'Assessments',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'assessments']),
                'active' => $activeTab === 'assessments',
            ],
        ];
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    private function instructorProfile(User $user): ?InstructorProfile
    {
        return $user->instructorProfile;
    }

    private function ownedClass(AcademicClass $class, ?InstructorProfile $instructorProfile): AcademicClass
    {
        abort_unless(
            $instructorProfile && $class->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access this class.'
        );

        return $class;
    }

    /**
     * @return list<string>
     */
    private function extractStudentNumbersFromImport(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            return $this->extractStudentNumbersFromRows($this->readRowsFromXlsxFile($path));
        }

        return $this->extractStudentNumbersFromRows($this->readRowsFromDelimitedFile($path));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRowsFromDelimitedFile(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(
                fn ($value) => is_string($value) ? trim($value) : '',
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRowsFromXlsxFile(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            return [];
        }

        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $currentRow = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $index = $this->xlsxColumnIndexFromReference($reference);

                while (count($currentRow) < $index) {
                    $currentRow[] = '';
                }

                $currentRow[] = $this->xlsxCellValue($cell, $sharedStrings);
            }

            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return list<string>
     */
    private function extractStudentNumbersFromRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $firstRow = array_map('trim', $rows[0]);
        $hasHeader = collect($firstRow)
            ->filter()
            ->map(fn (string $value) => Str::lower($value))
            ->contains('student_number');

        $studentNumbers = collect($hasHeader ? array_slice($rows, 1) : $rows)
            ->map(function (array $row) use ($firstRow, $hasHeader) {
                if ($hasHeader) {
                    $headerIndex = collect($firstRow)
                        ->search(fn ($value) => Str::lower((string) $value) === 'student_number');

                    return $headerIndex !== false ? trim((string) ($row[$headerIndex] ?? '')) : '';
                }

                return trim((string) ($row[0] ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $studentNumbers;
    }

    /**
     * @return list<string>
     */
    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($sharedStringsXml) || $sharedStringsXml === '') {
            return [];
        }

        $sharedStrings = simplexml_load_string($sharedStringsXml);

        if (! $sharedStrings instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($sharedStrings->si as $item) {
            if (isset($item->t)) {
                $strings[] = trim((string) $item->t);

                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            $strings[] = trim($text);
        }

        return $strings;
    }

    private function xlsxColumnIndexFromReference(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = trim((string) ($cell->v ?? ''));

        if ($type === 's') {
            return trim((string) ($sharedStrings[(int) $value] ?? ''));
        }

        return $value;
    }

    /**
     * @param  list<string>  $studentNumbers
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     summary: array<string, int>,
     *     ready_student_profile_ids: list<int>
     * }
     */
    private function buildImportPreview(AcademicClass $class, array $studentNumbers): array
    {
        $studentProfiles = StudentProfile::query()
            ->with(['user.roles', 'program.college'])
            ->whereIn('student_number', $studentNumbers)
            ->get()
            ->keyBy('student_number');

        $enrolledIds = $class->students()
            ->pluck('student_profiles.student_profile_id')
            ->all();

        $rows = [];
        $readyStudentProfileIds = [];
        $summary = [
            'total' => count($studentNumbers),
            'ready' => 0,
            'already_enrolled' => 0,
            'inactive' => 0,
            'not_found' => 0,
        ];

        foreach ($studentNumbers as $studentNumber) {
            $studentProfile = $studentProfiles->get($studentNumber);

            if (! $studentProfile || ! $studentProfile->user || ! $studentProfile->user->hasRole('student')) {
                $summary['not_found']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => 'No matching student account',
                    'program_name' => 'Unavailable',
                    'status_badge' => 'Not Found',
                    'status_class' => 'text-bg-secondary',
                ];

                continue;
            }

            if ($studentProfile->user->status !== 'active') {
                $summary['inactive']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => $studentProfile->user->displayName(),
                    'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                    'status_badge' => 'Inactive',
                    'status_class' => 'text-bg-warning',
                ];

                continue;
            }

            if (in_array($studentProfile->student_profile_id, $enrolledIds, true)) {
                $summary['already_enrolled']++;
                $rows[] = [
                    'student_number' => $studentNumber,
                    'student_name' => $studentProfile->user->displayName(),
                    'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                    'status_badge' => 'Already Enrolled',
                    'status_class' => 'text-bg-info',
                ];

                continue;
            }

            $summary['ready']++;
            $readyStudentProfileIds[] = $studentProfile->student_profile_id;
            $rows[] = [
                'student_number' => $studentNumber,
                'student_name' => $studentProfile->user->displayName(),
                'program_name' => $studentProfile->program?->program_name ?? 'Not assigned',
                'status_badge' => 'Ready',
                'status_class' => 'text-bg-success',
            ];
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
            'ready_student_profile_ids' => array_values(array_unique($readyStudentProfileIds)),
        ];
    }

    private function pullImportPreview(Request $request, AcademicClass $class): ?array
    {
        $importToken = $request->query('import_token');

        if (! is_string($importToken) || $importToken === '') {
            return null;
        }

        $storedImport = $request->session()->get("class_student_imports.{$importToken}");

        if (! $storedImport || ($storedImport['class_id'] ?? null) !== $class->class_id) {
            return null;
        }

        return [
            'token' => $importToken,
            'rows' => $storedImport['rows'] ?? [],
            'summary' => $storedImport['summary'] ?? [],
        ];
    }
}
