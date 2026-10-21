<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentAccountImportService
{
    public function __construct(
        private readonly TabularFileReader $fileReader,
        private readonly UserAccountService $accounts,
    ) {}

    /**
     * @param  Collection<int, Program>  $programs
     */
    public function previewUpload(Request $request, Collection $programs, string $scope): string
    {
        $validated = $request->validateWithBag('studentImport', [
            'import_program_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($programs): void {
                    if (! $programs->contains('program_id', (int) $value)) {
                        $fail('The selected program is invalid or outside your assigned scope.');
                    }
                },
            ],
            'student_file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,txt,xlsx',
            ],
        ]);

        $extension = Str::lower((string) $validated['student_file']->getClientOriginalExtension());
        $rows = $this->fileReader->read(
            $validated['student_file']->getRealPath(),
            $extension
        );
        $program = $programs->firstWhere('program_id', (int) $validated['import_program_id']);
        $preview = $this->buildPreview($rows, $program);
        $token = (string) Str::uuid();

        $request->session()->put($this->sessionKey($token), [
            'actor_id' => (int) $request->user()->id,
            'scope' => $scope,
            'file_extension' => $extension,
            'ready_rows' => $preview['ready_rows'],
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return $token;
    }

    public function previewForRequest(Request $request, string $scope): ?array
    {
        $token = (string) $request->query('import_token', '');

        if ($token === '' || ! Str::isUuid($token)) {
            return null;
        }

        $storedImport = $request->session()->get($this->sessionKey($token));

        if (! $this->storedImportIsValid($storedImport, $request, $scope)) {
            $request->session()->forget($this->sessionKey($token));

            return null;
        }

        return [
            'token' => $token,
            'rows' => $storedImport['rows'] ?? [],
            'summary' => $storedImport['summary'] ?? [],
        ];
    }

    /** Save and validate edits in the session without creating accounts. */
    public function review(Request $request, Collection $programs, string $scope): string
    {
        $validated = $request->validateWithBag('studentImport', ['import_token' => ['required', 'uuid']]);
        $oldKey = $this->sessionKey($validated['import_token']);
        $storedImport = $request->session()->get($oldKey);
        if (! $this->storedImportIsValid($storedImport, $request, $scope)) {
            $this->throwValidationError('The import preview has expired. Upload the file again.');
        }

        $preview = $this->validateRows($this->draftRowsFromRequest($request, $storedImport), $programs);
        $token = (string) Str::uuid();
        $request->session()->put($this->sessionKey($token), array_replace($storedImport, [
            'rows' => $preview['rows'], 'ready_rows' => $preview['ready_rows'], 'summary' => $preview['summary'],
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]));
        // An older tab must not create accounts from a superseded draft.
        $request->session()->forget($oldKey);

        return $token;
    }

    /**
     * @param  Collection<int, Program>  $programs
     * @param  Closure(array{created_count: int, initial_password_emails_sent: int}): void  $onCommitted
     */
    public function confirm(Request $request, Collection $programs, string $scope, Closure $onCommitted): void
    {
        $validated = $request->validate([
            'import_token' => ['required', 'uuid'],
        ]);
        $token = $validated['import_token'];
        $sessionKey = $this->sessionKey($token);
        $storedImport = $request->session()->get($sessionKey);

        if (! $this->storedImportIsValid($storedImport, $request, $scope)) {
            $request->session()->forget($sessionKey);
            $this->throwValidationError('The import preview has expired. Upload the file again.');
        }

        if ($request->exists('draft_rows')) {
            $submittedRows = $this->draftRowsFromRequest($request, $storedImport);
            $savedRows = collect($storedImport['rows'])->map(fn (array $row): array => $this->editableRow($row));
            if ($submittedRows->all() !== $savedRows->all()) {
                $this->throwValidationError('Your edits have not been validated. Select Save & Validate before creating accounts.');
            }
        }

        $rows = collect($storedImport['ready_rows'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values();

        if ($rows->isEmpty()) {
            $this->throwValidationError('There are no valid student accounts to import.');
        }

        $programIds = $programs->pluck('program_id')->map(fn ($id) => (int) $id)->all();
        $hasInvalidProgram = $rows->contains(
            fn (array $row) => ! in_array((int) ($row['program_id'] ?? 0), $programIds, true)
        );
        $emailConflict = User::query()->whereIn(DB::raw('LOWER(email)'), $rows->pluck('email')->map(fn ($value) => Str::lower($value)))->exists();
        $numberConflict = StudentProfile::query()->whereIn(DB::raw('LOWER(student_number)'), $rows->pluck('student_number')->map(fn ($value) => Str::lower($value)))->exists();

        if ($hasInvalidProgram || $emailConflict || $numberConflict) {
            $request->session()->forget($sessionKey);
            $this->throwValidationError('Some records changed after the preview. Upload the file again to refresh the validation.');
        }

        $studentRole = Role::query()->where('role_name', 'student')->firstOrFail();
        $format = match ($storedImport['file_extension'] ?? null) {
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
            default => 'file',
        };
        $createdAccounts = DB::transaction(function () use ($rows, $studentRole, $format): array {
            $accounts = [];

            foreach ($rows as $row) {
                $accountData = [
                    'base_role' => 'student',
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'student_number' => $row['student_number'],
                ];
                $initialPassword = $this->accounts->initialPasswordFor($accountData);
                $createdUser = User::create([
                    'name' => collect([$row['first_name'], $row['middle_name'], $row['last_name']])->filter()->implode(' '),
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?: null,
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'password' => $initialPassword,
                    'must_change_password' => true,
                    'status' => $row['status'],
                ]);

                $createdUser->roles()->attach($studentRole->role_id);
                StudentProfile::create([
                    'user_id' => $createdUser->id,
                    'program_id' => $row['program_id'],
                    'student_number' => $row['student_number'],
                ]);
                AuditLogger::logOrFail(
                    'CREATE',
                    'Users',
                    "Created student account for {$createdUser->displayName()} ({$createdUser->email}) via {$format} import",
                    $createdUser,
                );
                $accounts[] = [
                    'user' => $createdUser,
                    'initial_password' => $initialPassword,
                ];
            }

            return $accounts;
        });

        // The HTTP audit transaction also has to commit before credentials leave
        // the app. A rolled-back import keeps its preview available for retry.
        DB::afterCommit(function () use ($request, $sessionKey, $createdAccounts, $scope, $onCommitted): void {
            $request->session()->forget($sessionKey);
            $sentInitialPasswordEmails = 0;
            foreach ($createdAccounts as $account) {
                if ($this->accounts->sendInitialPasswordEmail($account['user'], $account['initial_password'])) {
                    $sentInitialPasswordEmails++;
                }
            }

            $result = [
                'created_count' => count($createdAccounts),
                'initial_password_emails_sent' => $sentInitialPasswordEmails,
            ];
            Log::info('Student accounts imported.', [
                'actor_id' => $request->user()->id,
                'scope' => $scope,
                ...$result,
            ]);

            $onCommitted($result);
        });
    }

    public function sampleCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['student_number', 'first_name', 'middle_name', 'last_name', 'email']);
            fputcsv($output, ['2024-00001', 'Juan', '', 'Dela Cruz', 'juan.delacruz@example.com']);
            fclose($output);
        }, 'student-account-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, ready_rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    private function buildPreview(array $rows, Program $program): array
    {
        if (empty($rows)) {
            $this->throwValidationError('The uploaded file does not contain any records.');
        }

        $headerMap = [];

        foreach ($rows[0] as $index => $header) {
            $normalizedHeader = $this->normalizeHeader($header);

            if ($normalizedHeader !== '') {
                $headerMap[$normalizedHeader] = $index;
            }
        }

        $requiredHeaders = ['student_number', 'first_name', 'last_name', 'email'];
        $missingHeaders = array_values(array_diff($requiredHeaders, array_keys($headerMap)));

        if (! empty($missingHeaders)) {
            $this->throwValidationError('Missing required columns: '.implode(', ', $missingHeaders).'.');
        }

        $records = collect(array_slice($rows, 1))
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->values();

        if ($records->isEmpty()) {
            $this->throwValidationError('The uploaded file has headers but no student records.');
        }

        if ($records->count() > 200) {
            $this->throwValidationError('Please limit each import to 200 student accounts or fewer.');
        }

        $valueAt = fn (array $row, string $column): string => trim((string) ($row[$headerMap[$column] ?? -1] ?? ''));
        $parsedRows = $records->map(fn (array $row, int $index): array => [
            'line' => $index + 2,
            'student_number' => $valueAt($row, 'student_number'),
            'first_name' => $valueAt($row, 'first_name'),
            'middle_name' => $valueAt($row, 'middle_name'),
            'last_name' => $valueAt($row, 'last_name'),
            'email' => Str::lower($valueAt($row, 'email')),
            'program_id' => (int) $program->program_id,
        ]);

        return $this->validateRows($parsedRows, collect([$program]));
    }

    private function validateRows(Collection $parsedRows, Collection $programs): array
    {
        $programsById = $programs->keyBy('program_id');
        $emailCounts = $parsedRows->pluck('email')->map(fn ($value) => Str::lower($value))->countBy();
        $numberCounts = $parsedRows->pluck('student_number')->map(fn ($value) => Str::lower($value))->countBy();
        $existingEmails = User::query()
            ->whereIn(DB::raw('LOWER(email)'), $parsedRows->pluck('email')->map(fn ($value) => Str::lower($value))->filter()->unique())
            ->pluck('email')->map(fn ($value) => Str::lower($value))->flip();
        $existingNumbers = StudentProfile::query()
            ->whereIn(DB::raw('LOWER(student_number)'), $parsedRows->pluck('student_number')->map(fn ($value) => Str::lower($value))->filter()->unique())
            ->pluck('student_number')->map(fn ($value) => Str::lower($value))->flip();
        $previewRows = [];
        $readyRows = [];
        $summary = ['total' => $parsedRows->count(), 'ready' => 0, 'duplicate' => 0, 'invalid' => 0];

        foreach ($parsedRows as $row) {
            $program = $programsById->get($row['program_id']);
            $errors = [];
            $isDuplicate = false;
            $normalizedEmail = Str::lower($row['email']);
            $normalizedNumber = Str::lower($row['student_number']);
            if (! $program) {
                $errors[] = 'The selected program is invalid or outside your assigned scope.';
            }
            foreach (['student_number', 'first_name', 'last_name', 'email'] as $field) {
                if ($row[$field] === '') {
                    $errors[] = str_replace('_', ' ', ucfirst($field)).' is required.';
                }
            }

            foreach (['student_number', 'first_name', 'middle_name', 'last_name', 'email'] as $field) {
                if (mb_strlen($row[$field]) > 255) {
                    $errors[] = str_replace('_', ' ', ucfirst($field)).' is too long.';
                }
            }

            if ($row['email'] !== '' && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Email format is invalid.';
            }

            if ($normalizedEmail !== '' && ($emailCounts->get($normalizedEmail, 0) > 1 || $existingEmails->has($normalizedEmail))) {
                $errors[] = 'Email is duplicated in the file or already exists.';
                $isDuplicate = true;
            }

            if ($normalizedNumber !== '' && ($numberCounts->get($normalizedNumber, 0) > 1 || $existingNumbers->has($normalizedNumber))) {
                $errors[] = 'Student number is duplicated in the file or already exists.';
                $isDuplicate = true;
            }

            if ($normalizedNumber !== '' && strlen(preg_replace('/\D+/', '', $normalizedNumber) ?? '') < 3) {
                $errors[] = 'Student number must contain at least 3 digits for the generated initial password.';
            }

            if (empty($errors) && $program) {
                $summary['ready']++;
                $readyRows[] = [
                    'student_number' => $row['student_number'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'program_id' => $program->program_id,
                    'status' => 'active',
                ];
                [$result, $resultClass] = ['Ready', 'text-bg-success'];
            } elseif ($isDuplicate) {
                $summary['duplicate']++;
                [$result, $resultClass] = ['Duplicate', 'text-bg-warning'];
            } else {
                $summary['invalid']++;
                [$result, $resultClass] = ['Invalid', 'text-bg-danger'];
            }

            $previewRows[] = [
                ...$this->editableRow($row),
                'line' => $row['line'],
                'student_number' => $row['student_number'],
                'student_name' => collect([$row['first_name'], $row['middle_name'], $row['last_name']])->filter()->implode(' '),
                'email' => $row['email'],
                'program_name' => $program?->program_name ?? 'Unavailable',
                'result' => $result,
                'result_class' => $resultClass,
                'message' => implode(' ', $errors),
            ];
        }

        return ['rows' => $previewRows, 'ready_rows' => $readyRows, 'summary' => $summary];
    }

    private function editableRow(array $row): array
    {
        return [
            'line' => (int) $row['line'],
            'student_number' => trim((string) ($row['student_number'] ?? '')),
            'first_name' => trim((string) ($row['first_name'] ?? '')),
            'middle_name' => trim((string) ($row['middle_name'] ?? '')),
            'last_name' => trim((string) ($row['last_name'] ?? '')),
            'email' => Str::lower(trim((string) ($row['email'] ?? ''))),
            'program_id' => (int) ($row['program_id'] ?? 0),
        ];
    }

    private function draftRowsFromRequest(Request $request, array $storedImport): Collection
    {
        // One JSON field also supports 200 editable rows without PHP's input-variable limit.
        $validated = $request->validateWithBag('studentImport', ['draft_rows' => ['required', 'string', 'json', 'max:1000000']]);
        $rows = json_decode($validated['draft_rows'], true, 512, JSON_THROW_ON_ERROR);
        $savedRows = $storedImport['rows'] ?? [];
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== count($savedRows) || count($rows) < 1 || count($rows) > 200) {
            $this->throwValidationError('The edited rows do not match this preview. Refresh the preview and try again.');
        }
        foreach ($rows as $index => &$row) {
            if (! is_array($row)) $this->throwValidationError('The edited student record is invalid.');
            foreach (['student_number', 'first_name', 'middle_name', 'last_name', 'email'] as $field) {
                if (isset($row[$field]) && ! is_string($row[$field])) $this->throwValidationError('The edited student fields must be text.');
            }
            $programId = $row['program_id'] ?? '';
            $row['program_id'] = is_int($programId) || (is_string($programId) && ctype_digit($programId)) ? (int) $programId : 0;
            $row['line'] = $savedRows[$index]['line'];
        }
        unset($row);

        return collect($rows)->map(fn (array $row): array => $this->editableRow($row));
    }

    private function storedImportIsValid(mixed $storedImport, Request $request, string $scope): bool
    {
        return is_array($storedImport)
            && (int) ($storedImport['actor_id'] ?? 0) === (int) $request->user()->id
            && hash_equals((string) ($storedImport['scope'] ?? ''), $scope)
            && (int) ($storedImport['expires_at'] ?? 0) >= now()->timestamp;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? '';

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower($header)), '_');
    }

    private function sessionKey(string $token): string
    {
        return "student_account_imports.{$token}";
    }

    private function throwValidationError(string $message): never
    {
        $exception = ValidationException::withMessages(['student_file' => $message]);
        $exception->errorBag = 'studentImport';

        throw $exception;
    }
}
