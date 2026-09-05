<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicClass;
use App\Models\ClassDetail;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait InstructorClassAccessHelper
{
    protected function ensureClassJoinAccess(AcademicClass $class): void
    {
        if ($class->join_token && $class->join_code) {
            return;
        }

        $class->forceFill(array_filter([
            'join_token' => $class->join_token ?: $this->generateClassJoinToken(),
            'join_code' => $class->join_code ?: $this->generateClassJoinCode(),
        ]))->save();
    }

    protected function generateClassJoinToken(): string
    {
        do {
            $token = Str::random(40);
        } while (AcademicClass::query()->where('join_token', $token)->exists());

        return $token;
    }

    protected function generateClassJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (AcademicClass::query()->where('join_code', $code)->exists());

        return $code;
    }

    protected function markJoinRequestApproved(AcademicClass $class, StudentProfile $studentProfile, int $responderId): void
    {
        $existingDetail = $class->classDetails()
            ->where('student_id', $studentProfile->student_profile_id)
            ->first();

        $class->syncClassDetailForStudent(
            $studentProfile,
            ClassDetail::STATUS_APPROVED,
            $existingDetail?->entry_method ?: ClassDetail::METHOD_MANUAL_ADD
        );
    }

    protected function extractStudentNumbersFromRows(array $rows): array
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

    protected function buildImportPreview(AcademicClass $class, array $studentNumbers): array
    {
        $studentProfiles = StudentProfile::query()
            ->with(['user.roles', 'program.department.college'])
            ->whereIn('student_number', $studentNumbers)
            ->get()
            ->keyBy('student_number');

        $enrolledIds = $class->enrolledStudentIds();

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

    protected function pullImportPreview(Request $request, AcademicClass $class): ?array
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
