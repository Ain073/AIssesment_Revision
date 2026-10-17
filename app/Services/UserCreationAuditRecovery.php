<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UserCreationAuditRecovery
{
    public const USER_AGENT = 'Import History Recovery';

    public const METHOD = 'profile_and_student_role_timestamp_batch_match';

    /**
     * Append account events supported by legacy import summaries. Never edit
     * accounts or original events; retain the matching evidence in metadata.
     */
    public function recover(): int
    {
        return DB::transaction(function (): int {
            $imports = DB::table('audit_logs')
                ->where('module', 'Users')->where('action', 'IMPORT')
                ->orderBy('created_at')->orderBy('audit_log_id')->get();
            $timestampCounts = $imports->countBy('created_at');
            $previousEnd = null;
            $inserted = 0;

            foreach ($imports as $import) {
                $end = CarbonImmutable::parse($import->created_at);
                // Accounts must be within a minute of completion and after
                // the previous import, including an unsupported/ambiguous one.
                $start = $end->subMinute();
                if ($previousEnd !== null && $previousEnd->greaterThan($start)) {
                    $start = $previousEnd;
                }
                $previousEnd = $end;

                if ($timestampCounts[$import->created_at] !== 1
                    || $import->user_id === null
                    || ! in_array($import->user_role, ['super_admin', 'admin_dean', 'department_chair'], true)
                    || ! DB::table('users')->where('id', $import->user_id)->exists()
                    || ! preg_match('/^Imported ([1-9][0-9]*) student account\/s via (CSV|XLSX)$/D', $import->description, $match)) {
                    continue;
                }

                $inserted += $this->recoverBatch($import, $start, $end, (int) $match[1], $match[2]);
            }

            return $inserted;
        });
    }

    private function recoverBatch(object $import, CarbonImmutable $start, CarbonImmutable $end, int $expected, string $format): int
    {
        $profiles = DB::table('student_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.created_at', '>', $start->toDateTimeString())
            ->where('p.created_at', '<=', $end->toDateTimeString())
            ->select(['p.user_id', 'p.created_at', 'p.updated_at', 'p.program_id', 'u.name', 'u.first_name', 'u.middle_name', 'u.last_name', 'u.email'])
            ->orderBy('p.user_id')->get();

        // A missing/deleted profile, extra manual creation, or overlapping batch
        // must not be guessed away. Programs can change later, so compare them
        // only for profiles that have never been updated since creation.
        if ($profiles->count() !== $expected
            || $profiles->unique('user_id')->count() !== $expected
            || $profiles->filter(fn ($profile) => $profile->updated_at === $profile->created_at)
                ->unique('program_id')->count() > 1
            || $profiles->contains(fn ($profile) => (int) $profile->user_id === (int) $import->user_id)) {
            return 0;
        }

        foreach ($profiles as $profile) {
            $studentRoleAttachedAtCreation = DB::table('user_roles as ur')
                ->join('roles as r', 'r.role_id', '=', 'ur.role_id')
                ->where('ur.user_id', $profile->user_id)
                ->where('r.role_name', 'student')
                ->where('ur.created_at', $profile->created_at)->exists();

            if (! $studentRoleAttachedAtCreation) {
                return 0;
            }
        }

        $existing = DB::table('audit_logs')
            ->where('module', 'Users')->where('action', 'CREATE')
            ->where('auditable_type', User::class)
            ->whereIn('auditable_id', $profiles->pluck('user_id'))
            ->where(fn ($query) => $query->whereNull('user_agent')->orWhere('user_agent', '!=', 'System Migration'))
            ->get();

        foreach ($existing as $event) {
            $metadata = json_decode($event->metadata ?? 'null', true);
            // An original per-account event or another matching source wins.
            // Do not attach the remaining accounts to a potentially mixed batch.
            if ($event->user_agent !== self::USER_AGENT
                || (int) ($metadata['source_import_audit_log_id'] ?? 0) !== (int) $import->audit_log_id
                || ($metadata['recovery_method'] ?? null) !== self::METHOD
                || (int) $event->user_id !== (int) $import->user_id
                || $event->user_role !== $import->user_role) {
                return 0;
            }
        }

        $existingIds = $existing->pluck('auditable_id')->map(fn ($id) => (int) $id)->all();
        $inserted = 0;
        foreach ($profiles as $profile) {
            if (in_array((int) $profile->user_id, $existingIds, true)) {
                continue;
            }

            $name = collect([$profile->first_name, $profile->middle_name, $profile->last_name])
                ->filter()->implode(' ') ?: $profile->name;

            DB::table('audit_logs')->insert([
                'user_id' => $import->user_id,
                'user_role' => $import->user_role,
                'module' => 'Users',
                'action' => 'CREATE',
                'description' => "Created student account for {$name} ({$profile->email}) via {$format} import",
                'auditable_type' => User::class,
                'auditable_id' => $profile->user_id,
                'ip_address' => null,
                'user_agent' => self::USER_AGENT,
                'created_at' => $profile->created_at,
                'metadata' => json_encode([
                    'source_import_audit_log_id' => (int) $import->audit_log_id,
                    'recovery_method' => self::METHOD,
                    'matching_window_start_exclusive' => $start->toDateTimeString(),
                    'matching_window_end_inclusive' => $end->toDateTimeString(),
                    'expected_batch_count' => $expected,
                    'timestamp_source' => 'student_profiles.created_at',
                    'recorded_at' => now()->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
            ]);
            $inserted++;
        }

        return $inserted;
    }
}
