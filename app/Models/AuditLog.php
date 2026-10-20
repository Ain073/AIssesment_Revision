<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $primaryKey = 'audit_log_id';

    // Audit logs are append-only — no updated_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'module',
        'description',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'created_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Keep original placeholders in storage, but prefer an original or
     * import-backed account event when displaying the audit trail.
     */
    public function scopeWithoutSupersededUserCreations(Builder $query): Builder
    {
        return $query->whereNot(function ($placeholder) {
            $placeholder->where('module', 'Users')->where('action', 'CREATE')
                ->where('auditable_type', User::class)
                ->whereNotNull('user_agent')->where('user_agent', 'System Migration')
                ->whereExists(function ($actual) {
                    $actual->selectRaw('1')->from('audit_logs as actual_creation')
                        ->whereColumn('actual_creation.auditable_id', 'audit_logs.auditable_id')
                        ->where('actual_creation.auditable_type', User::class)
                        ->where('actual_creation.module', 'Users')->where('actual_creation.action', 'CREATE')
                        ->where(fn ($agent) => $agent->whereNull('actual_creation.user_agent')
                            ->orWhere('actual_creation.user_agent', '!=', 'System Migration'));
                });
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Human-readable label for the user_role field.
     */
    public function userRoleLabel(): string
    {
        if ($this->isSystemMigration()) {
            if ($this->isHistoricalEnrollment() || $this->isHistoricalInstructorEvent()) {
                return $this->user_role === 'instructor' ? 'Instructor' : 'Unknown';
            }

            return $this->isHistoricalAccountCreation()
                ? 'Unknown'
                : ($this->isHistoricalSubmission() ? 'Student' : 'System');
        }

        return match ($this->user_role) {
            'super_admin' => 'Admin',
            'admin_dean' => 'Dean',
            'department_chair' => 'Department Chair',
            'instructor' => 'Instructor',
            'student' => 'Student',
            default => 'System',
        };
    }

    /**
     * Display name of the actor — falls back to "Deleted Account" if user was removed.
     */
    public function actorName(): string
    {
        if (! $this->isSystemMigration() && filled($this->metadata['actor']['name'] ?? null)) {
            return $this->metadata['actor']['name'];
        }

        if ($this->isSystemMigration()) {
            if ($this->isHistoricalAccountCreation()) {
                return 'Creator unavailable';
            }

            if ($this->isHistoricalEnrollment()) {
                return $this->user?->displayName()
                    ?? ($this->user_role === 'instructor' ? 'Deleted Account' : 'Enrolling user unavailable');
            }

            if ($this->isHistoricalInstructorEvent()) {
                return $this->user?->displayName() ?? 'Deleted Account';
            }

            return $this->isHistoricalSubmission()
                ? ($this->user?->displayName() ?? 'Student identity unavailable')
                : 'System Migration';
        }

        return $this->user?->displayName() ?? 'Deleted Account';
    }

    public function actorSubtitle(): string
    {
        if (! $this->isSystemMigration() && filled($this->metadata['actor']['email'] ?? null)) {
            return $this->metadata['actor']['email'];
        }

        if ($this->isSystemMigration()) {
            return $this->isHistoricalSubmission() || $this->isHistoricalEnrollment() || $this->isHistoricalInstructorEvent()
                ? ($this->user?->email ?? '') : '';
        }

        return $this->user?->email ?? 'Deleted account';
    }

    public function isSystemMigration(): bool
    {
        return $this->user_agent === 'System Migration';
    }

    public function isHistoricalEnrollment(): bool
    {
        return $this->isSystemMigration()
            && $this->action === 'ENROLL'
            && $this->module === 'Classes'
            && $this->auditable_type === ClassDetail::class
            && ($this->metadata['recovery_method'] ?? null) === 'approved_class_roster';
    }

    private function isHistoricalSubmission(): bool
    {
        // Submission backfills retain the submitting student's account reference.
        return $this->isSystemMigration()
            && $this->action === 'SUBMIT'
            && $this->module === 'Assessments'
            && $this->auditable_type === Submission::class;
    }

    private function isHistoricalInstructorEvent(): bool
    {
        if (! $this->isSystemMigration() || $this->user_role !== 'instructor') {
            return false;
        }

        return match ([$this->module, $this->action, $this->auditable_type]) {
            ['Classes', 'CREATE', AcademicClass::class],
            ['Assessments', 'CREATE', Assessment::class],
            ['Assessments', 'PUBLISH', PublishAssessment::class],
            ['Reports', 'FINALIZE', Report::class] => true,
            default => false,
        };
    }

    private function isHistoricalAccountCreation(): bool
    {
        return $this->isSystemMigration()
            && $this->action === 'CREATE'
            && $this->module === 'Users'
            && $this->auditable_type === User::class;
    }

    public function needsCurrentAccountLabel(): bool
    {
        return $this->module === 'Users'
            && $this->action === 'UPDATE'
            && $this->auditable_type === User::class
            && $this->auditable_id !== null
            && preg_match('/^Updated (student|instructor) account(?: details)? \(Status:/', (string) $this->description) === 1;
    }

    public function currentAccountLabel(): ?string
    {
        // Generic old events have no identity snapshot. Only show their verified link,
        // labelled as current information; never infer a target from the actor.
        if (! $this->needsCurrentAccountLabel() || ! $this->relationLoaded('auditable')) {
            return null;
        }

        $account = $this->getRelation('auditable');
        if (! $account instanceof User) {
            return null;
        }

        $studentNumber = $account->studentProfile?->student_number;
        $employeeNumber = $account->instructorProfile?->employee_number;
        $identifier = filled($studentNumber) ? "Student No.: {$studentNumber}"
            : (filled($employeeNumber) ? "Employee No.: {$employeeNumber}" : null);

        return $account->displayName().($identifier !== null ? " ({$identifier})" : '');
    }

    public function displayDescription(): string
    {
        if ($this->isHistoricalEnrollment()) {
            return preg_replace('/ \(recovered from saved roster\)\.$/', '', (string) $this->description)
                ?? (string) $this->description;
        }

        if ($this->isHistoricalAccountCreation()) {
            return preg_replace(
                '/^Reconstructed creation entry for account (.+); original creator unavailable\.$/s',
                'User account for $1 created.',
                (string) $this->description,
            ) ?? (string) $this->description;
        }

        return (string) $this->description;
    }
}
