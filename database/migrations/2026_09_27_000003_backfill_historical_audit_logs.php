<?php

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        // 1. Backfill User Creations
        try {
            User::query()
                ->with('roles')
                ->chunk(100, function ($users): void {
                    foreach ($users as $user) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', User::class)
                            ->where('auditable_id', $user->id)
                            ->where('action', 'CREATE')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $role = $user->roles->first()?->role_name ?? 'user';
                        $displayName = trim("{$user->first_name} {$user->last_name}") ?: $user->email;

                        AuditLog::query()->create([
                            'user_id' => $user->id,
                            'user_role' => $role,
                            'action' => 'CREATE',
                            'module' => 'Users',
                            'description' => "User account for {$displayName} ({$user->email}) created.",
                            'auditable_type' => User::class,
                            'auditable_id' => $user->id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $user->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }

        // 2. Backfill Class Creations
        try {
            AcademicClass::query()
                ->with(['instructorProfile.user', 'contextDetail'])
                ->chunk(100, function ($classes): void {
                    foreach ($classes as $class) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', AcademicClass::class)
                            ->where('auditable_id', $class->class_id)
                            ->where('action', 'CREATE')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $instructorUser = $class->instructorProfile?->user;
                        $className = $class->class_name ?: $class->displayName();

                        AuditLog::query()->create([
                            'user_id' => $instructorUser?->id,
                            'user_role' => 'instructor',
                            'action' => 'CREATE',
                            'module' => 'Classes',
                            'description' => "Created class '{$className}'" . ($class->join_code ? " ({$class->join_code})" : ''),
                            'auditable_type' => AcademicClass::class,
                            'auditable_id' => $class->class_id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $class->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }

        // 3. Backfill Assessment Creations
        try {
            Assessment::query()
                ->with('instructorProfile.user')
                ->chunk(100, function ($assessments): void {
                    foreach ($assessments as $assessment) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', Assessment::class)
                            ->where('auditable_id', $assessment->assessment_id)
                            ->where('action', 'CREATE')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $instructorUser = $assessment->instructorProfile?->user;

                        AuditLog::query()->create([
                            'user_id' => $instructorUser?->id,
                            'user_role' => 'instructor',
                            'action' => 'CREATE',
                            'module' => 'Assessments',
                            'description' => "Created assessment '{$assessment->title}'",
                            'auditable_type' => Assessment::class,
                            'auditable_id' => $assessment->assessment_id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $assessment->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }

        // 4. Backfill Publish Assessment Actions
        try {
            PublishAssessment::query()
                ->with(['assessment.instructorProfile.user', 'classDetail.class'])
                ->chunk(100, function ($publishes): void {
                    foreach ($publishes as $publish) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', PublishAssessment::class)
                            ->where('auditable_id', $publish->publish_assessment_id)
                            ->where('action', 'PUBLISH')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $instructorUser = $publish->assessment?->instructorProfile?->user;
                        $title = $publish->assessment?->title ?? 'Assessment';
                        $targetClass = $publish->classDetail?->class?->class_name ?? 'class';

                        AuditLog::query()->create([
                            'user_id' => $instructorUser?->id,
                            'user_role' => 'instructor',
                            'action' => 'PUBLISH',
                            'module' => 'Assessments',
                            'description' => "Published assessment '{$title}' to {$targetClass}",
                            'auditable_type' => PublishAssessment::class,
                            'auditable_id' => $publish->publish_assessment_id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $publish->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }

        // 5. Backfill Student Submissions
        try {
            Submission::query()
                ->where('status', Submission::STATUS_SUBMITTED)
                ->with(['studentProfile.user', 'publishAssessment.assessment'])
                ->chunk(100, function ($submissions): void {
                    foreach ($submissions as $submission) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', Submission::class)
                            ->where('auditable_id', $submission->submission_id)
                            ->where('action', 'SUBMIT')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $studentUser = $submission->studentProfile?->user;
                        $title = $submission->publishAssessment?->assessment?->title ?? 'Assessment';

                        AuditLog::query()->create([
                            'user_id' => $studentUser?->id,
                            'user_role' => 'student',
                            'action' => 'SUBMIT',
                            'module' => 'Assessments',
                            'description' => "Submitted attempt for '{$title}'",
                            'auditable_type' => Submission::class,
                            'auditable_id' => $submission->submission_id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $submission->submitted_at ?? $submission->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }

        // 6. Backfill Finalized Reports
        try {
            Report::query()
                ->where('report_status', Report::STATUS_FINALIZED)
                ->with(['publishAssessment.assessment.instructorProfile.user'])
                ->chunk(100, function ($reports): void {
                    foreach ($reports as $report) {
                        $exists = AuditLog::query()
                            ->where('auditable_type', Report::class)
                            ->where('auditable_id', $report->report_id)
                            ->where('action', 'FINALIZE')
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $instructorUser = $report->publishAssessment?->assessment?->instructorProfile?->user;
                        $title = $report->publishAssessment?->assessment?->title ?? 'Assessment';
                        $type = ucfirst($report->report_type);

                        AuditLog::query()->create([
                            'user_id' => $instructorUser?->id,
                            'user_role' => 'instructor',
                            'action' => 'FINALIZE',
                            'module' => 'Reports',
                            'description' => "Finalized {$type} Report for '{$title}'",
                            'auditable_type' => Report::class,
                            'auditable_id' => $report->report_id,
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System Migration',
                            'created_at' => $report->updated_at ?? $report->created_at ?? now(),
                        ]);
                    }
                });
        } catch (\Throwable) {
            // Safe fallback
        }
    }

    public function down(): void
    {
        // Safe: append-only audit trail does not delete logs on rollback
    }
};
