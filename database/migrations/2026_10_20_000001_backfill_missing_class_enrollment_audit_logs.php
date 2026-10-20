<?php

use App\Models\AcademicClass;
use App\Models\AuditLog;
use App\Models\ClassDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasTable('class_details')) {
            return;
        }

        DB::transaction(function (): void {
            $recordedAt = now();

            ClassDetail::query()
                ->whereNotNull('student_id')
                ->where('status', ClassDetail::STATUS_APPROVED)
                ->with(['class', 'studentProfile.user'])
                ->chunkById(100, function ($details) use ($recordedAt): void {
                    $existing = AuditLog::query()
                        ->where('module', 'Classes')->where('action', 'ENROLL')
                        ->where(function ($query) use ($details): void {
                            $query->where(function ($query) use ($details): void {
                                $query->where('auditable_type', ClassDetail::class)
                                    ->whereIn('auditable_id', $details->modelKeys());
                            })->orWhere(function ($query) use ($details): void {
                                $query->where('auditable_type', AcademicClass::class)
                                    ->whereIn('auditable_id', $details->pluck('class_id')->unique());
                            });
                        })->get();

                    $recordedIds = $existing->where('auditable_type', ClassDetail::class)
                        ->pluck('auditable_id')->map(fn ($id) => (int) $id)->all();
                    $legacyByClass = $existing->where('auditable_type', AcademicClass::class)
                        ->groupBy('auditable_id');

                    foreach ($details as $detail) {
                        if (in_array((int) $detail->getKey(), $recordedIds, true)) {
                            continue;
                        }

                        $studentNumber = $detail->studentProfile?->student_number;
                        // Original ENROLL events reference the class, so match the
                        // student number too, excluding events before this roster record.
                        $legacyExists = filled($studentNumber)
                            && $legacyByClass->get($detail->class_id, collect())->contains(
                                fn ($event) => preg_match('/^Enrolled student .+ \('.preg_quote($studentNumber, '/').'\) into /s', $event->description) === 1
                                    && ($detail->created_at === null || $event->created_at->greaterThanOrEqualTo($detail->created_at))
                            );

                        if ($legacyExists) {
                            continue;
                        }

                        $studentName = $detail->studentProfile?->user?->displayName()
                            ?: 'Student profile #'.$detail->student_id;
                        $studentLabel = $studentName.(filled($studentNumber) ? " ({$studentNumber})" : '');
                        $className = $detail->class?->class_name ?: 'Class #'.$detail->class_id;
                        $rosterCreatedAt = $detail->getRawOriginal('created_at');

                        DB::table('audit_logs')->insert([
                            // Roster ownership does not identify who approved/added the student.
                            'user_id' => null,
                            'user_role' => null,
                            'action' => 'ENROLL',
                            'module' => 'Classes',
                            'description' => "Enrolled student {$studentLabel} into {$className} (recovered from saved roster).",
                            'auditable_type' => ClassDetail::class,
                            'auditable_id' => $detail->getKey(),
                            'ip_address' => null,
                            'user_agent' => 'System Migration',
                            // created_at can be a join-request date. The audit view
                            // labels this as the roster date, never as an approval date.
                            'created_at' => $rosterCreatedAt ?? $recordedAt,
                            'metadata' => json_encode([
                                'recovery_method' => 'approved_class_roster',
                                'class_id' => (int) $detail->class_id,
                                'student_profile_id' => (int) $detail->student_id,
                                'entry_method' => $detail->entry_method,
                                'timestamp_source' => $rosterCreatedAt !== null ? 'class_details.created_at' : null,
                                'roster_created_at' => $rosterCreatedAt,
                                'roster_updated_at' => $detail->getRawOriginal('updated_at'),
                                'recorded_at' => $recordedAt->toIso8601String(),
                            ], JSON_THROW_ON_ERROR),
                        ]);
                    }
                }, 'class_details_id');
        });
    }

    public function down(): void
    {
        // Keep recovered events and their provenance in the append-only audit trail.
    }
};
