<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentAccountImportService
{
    public function __construct(private readonly TabularFileReader $fileReader) {}

    /**
     * @param  Collection<int, Program>  $programs
     */
    public function previewUpload(Request $request, Collection $programs, string $scope): string
    {
        $validated = $request->validateWithBag('studentImport', [
            'student_file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,txt,xlsx',
            ],
        ]);

        $rows = $this->fileReader->read(
            $validated['student_file']->getRealPath(),
            Str::lower((string) $validated['student_file']->getClientOriginalExtension())
        );
        $preview = $this->buildPreview($rows, $programs);
        $token = (string) Str::uuid();

        $request->session()->put($this->sessionKey($token), [
            'actor_id' => (int) $request->user()->id,
            'scope' => $scope,
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

    /**
     * @param  Collection<int, Program>  $programs
     * @return array{created_count: int, setup_links_sent: int}
     */
    public function confirm(Request $request, Collection $programs, string $scope): array
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
        $emailConflict = User::query()->whereIn('email', $rows->pluck('email'))->exists();
        $numberConflict = StudentProfile::query()->whereIn('student_number', $rows->pluck('student_number'))->exists();

        if ($hasInvalidProgram || $emailConflict || $numberConflict) {
            $request->session()->forget($sessionKey);
            $this->throwValidationError('Some records changed after the preview. Upload the file again to refresh the validation.');
        }

        $studentRole = Role::query()->where('role_name', 'student')->firstOrFail();
        $createdEmails = DB::transaction(function () use ($rows, $studentRole): array {
            $emails = [];

            foreach ($rows as $row) {
                $createdUser = User::create([
                    'name' => collect([$row['first_name'], $row['middle_name'], $row['last_name']])->filter()->implode(' '),
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?: null,
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'password' => Str::random(40),
                    'status' => $row['status'],
                ]);

                $createdUser->roles()->attach($studentRole->role_id);
                StudentProfile::create([
                    'user_id' => $createdUser->id,
                    'program_id' => $row['program_id'],
                    'student_number' => $row['student_number'],
                ]);
                $emails[] = $createdUser->email;
            }

            return $emails;
        });

        $request->session()->forget($sessionKey);
        $sentLinks = 0;

        foreach ($createdEmails as $email) {
            try {
                if (Password::sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT) {
                    $sentLinks++;
                }
            } catch (Throwable $exception) {
                Log::warning('Student account setup email could not be sent.', [
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Student accounts imported.', [
            'actor_id' => $request->user()->id,
            'scope' => $scope,
            'created_count' => count($createdEmails),
            'setup_links_sent' => $sentLinks,
        ]);

        return [
            'created_count' => count($createdEmails),
            'setup_links_sent' => $sentLinks,
        ];
    }

    public function sampleCsv(Program $program): StreamedResponse
    {
        return response()->streamDownload(function () use ($program): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['student_number', 'first_name', 'middle_name', 'last_name', 'email', 'program_id', 'program', 'status']);
            fputcsv($output, ['2024-00001', 'Juan', '', 'Dela Cruz', 'juan.delacruz@example.com', $program->program_id, $program->program_name, 'active']);
            fclose($output);
        }, 'student-account-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  Collection<int, Program>  $programs
     * @return array{rows: array<int, array<string, mixed>>, ready_rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    private function buildPreview(array $rows, Collection $programs): array
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

        if (! array_key_exists('program', $headerMap) && ! array_key_exists('program_id', $headerMap)) {
            $missingHeaders[] = 'program or program_id';
        }

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
            'program_id' => $valueAt($row, 'program_id'),
            'program' => $valueAt($row, 'program'),
            'status' => Str::lower($valueAt($row, 'status') ?: 'active'),
        ]);

        $emailCounts = $parsedRows->pluck('email')->map(fn ($value) => Str::lower($value))->countBy();
        $numberCounts = $parsedRows->pluck('student_number')->map(fn ($value) => Str::lower($value))->countBy();
        $existingEmails = User::query()
            ->whereIn('email', $parsedRows->pluck('email')->filter()->unique())
            ->pluck('email')->map(fn ($value) => Str::lower($value))->flip();
        $existingNumbers = StudentProfile::query()
            ->whereIn('student_number', $parsedRows->pluck('student_number')->filter()->unique())
            ->pluck('student_number')->map(fn ($value) => Str::lower($value))->flip();
        $programsById = $programs->keyBy(fn (Program $program) => (int) $program->program_id);
        $programsByName = $programs->groupBy(fn (Program $program) => Str::lower(trim($program->program_name)));
        $previewRows = [];
        $readyRows = [];
        $summary = ['total' => $parsedRows->count(), 'ready' => 0, 'duplicate' => 0, 'invalid' => 0];

        foreach ($parsedRows as $row) {
            $errors = [];
            $isDuplicate = false;
            $normalizedEmail = Str::lower($row['email']);
            $normalizedNumber = Str::lower($row['student_number']);
            $program = null;

            if ($row['program_id'] !== '') {
                $program = ctype_digit($row['program_id'])
                    ? $programsById->get((int) $row['program_id'])
                    : null;

                if (! $program) {
                    $errors[] = 'Program ID is invalid or outside your assigned scope.';
                }
            } elseif ($row['program'] !== '') {
                $matchingPrograms = $programsByName->get(Str::lower($row['program']), collect());

                if ($matchingPrograms->count() === 1) {
                    $program = $matchingPrograms->first();
                } elseif ($matchingPrograms->count() > 1) {
                    $errors[] = 'Program name matches multiple records. Add the program_id column.';
                } else {
                    $errors[] = 'Program is outside your assigned scope or does not match an existing program.';
                }
            }

            foreach (['student_number', 'first_name', 'last_name', 'email'] as $field) {
                if ($row[$field] === '') {
                    $errors[] = str_replace('_', ' ', ucfirst($field)).' is required.';
                }
            }

            if ($row['program_id'] === '' && $row['program'] === '') {
                $errors[] = 'Program or program ID is required.';
            }

            foreach (['student_number', 'first_name', 'middle_name', 'last_name', 'email'] as $field) {
                if (mb_strlen($row[$field]) > 255) {
                    $errors[] = str_replace('_', ' ', ucfirst($field)).' is too long.';
                }
            }

            if ($row['email'] !== '' && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Email format is invalid.';
            }

            if (! in_array($row['status'], ['active', 'inactive'], true)) {
                $errors[] = 'Status must be active or inactive.';
            }

            if ($normalizedEmail !== '' && ($emailCounts->get($normalizedEmail, 0) > 1 || $existingEmails->has($normalizedEmail))) {
                $errors[] = 'Email is duplicated in the file or already exists.';
                $isDuplicate = true;
            }

            if ($normalizedNumber !== '' && ($numberCounts->get($normalizedNumber, 0) > 1 || $existingNumbers->has($normalizedNumber))) {
                $errors[] = 'Student number is duplicated in the file or already exists.';
                $isDuplicate = true;
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
                    'status' => $row['status'],
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
                'line' => $row['line'],
                'student_number' => $row['student_number'],
                'student_name' => collect([$row['first_name'], $row['middle_name'], $row['last_name']])->filter()->implode(' '),
                'email' => $row['email'],
                'program_name' => $program?->program_name ?? ($row['program'] ?: $row['program_id']),
                'status' => ucfirst($row['status']),
                'result' => $result,
                'result_class' => $resultClass,
                'message' => implode(' ', $errors),
            ];
        }

        return ['rows' => $previewRows, 'ready_rows' => $readyRows, 'summary' => $summary];
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
