<?php

use App\Models\AuditLog;
use App\Models\ClassDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['audit_logs', 'class_details', 'instructor_profiles', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        DB::transaction(function (): void {
            AuditLog::query()
                ->where('module', 'Classes')->where('action', 'ENROLL')
                ->where('user_agent', 'System Migration')
                ->where('auditable_type', ClassDetail::class)
                ->whereNull('user_id')
                ->chunkById(100, function ($events): void {
                    $events = $events->filter(fn ($event) => $event->isHistoricalEnrollment());
                    $instructors = DB::table('class_details as detail')
                        ->join('instructor_profiles as profile', 'profile.instructor_profile_id', '=', 'detail.instructor_id')
                        ->join('users', 'users.id', '=', 'profile.user_id')
                        ->whereIn('detail.class_details_id', $events->pluck('auditable_id'))
                        ->get(['detail.class_details_id', 'detail.instructor_id', 'profile.user_id'])
                        ->keyBy('class_details_id');

                    foreach ($events as $event) {
                        $instructor = $instructors->get($event->auditable_id);
                        if (! $instructor) {
                            continue;
                        }

                        $metadata = $event->metadata;
                        $metadata['actor_source'] = 'class_details.instructor_id';
                        $metadata['instructor_profile_id'] = (int) $instructor->instructor_id;
                        $metadata['actor_recovered_at'] = now()->toIso8601String();

                        // Only enrich synthetic enrollments with a missing actor.
                        // Keep their original description, timestamps, and recovery evidence.
                        DB::table('audit_logs')->where('audit_log_id', $event->getKey())
                            ->whereNull('user_id')->update([
                                'user_id' => $instructor->user_id,
                                'user_role' => 'instructor',
                                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                            ]);
                    }
                }, 'audit_log_id');
        });
    }

    public function down(): void
    {
        // Keep restored actor links and their provenance on deployment rollback.
    }
};
