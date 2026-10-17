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
            return $this->isHistoricalAccountCreation()
                ? 'Unknown'
                : ($this->isHistoricalSubmission() ? 'Student' : 'System');
        }

        return match ($this->user_role) {
            'super_admin'      => 'Admin',
            'admin_dean'       => 'Dean',
            'department_chair' => 'Department Chair',
            'instructor'       => 'Instructor',
            'student'          => 'Student',
            default            => 'System',
        };
    }

    /**
     * Display name of the actor — falls back to "Deleted Account" if user was removed.
     */
    public function actorName(): string
    {
        if ($this->isSystemMigration()) {
            if ($this->isHistoricalAccountCreation()) {
                return 'Creator unavailable';
            }

            return $this->isHistoricalSubmission()
                ? ($this->user?->displayName() ?? 'Student identity unavailable')
                : 'System Migration';
        }

        return $this->user?->displayName() ?? 'Deleted Account';
    }

    public function actorSubtitle(): string
    {
        if ($this->isSystemMigration()) {
            return $this->isHistoricalSubmission() ? ($this->user?->email ?? '') : '';
        }

        return $this->user?->email ?? 'Deleted account';
    }

    public function isSystemMigration(): bool
    {
        return $this->user_agent === 'System Migration';
    }

    private function isHistoricalSubmission(): bool
    {
        // Only submission backfills identify a student; other backfills may identify a record owner.
        return $this->isSystemMigration()
            && $this->action === 'SUBMIT'
            && $this->module === 'Assessments'
            && $this->auditable_type === Submission::class;
    }

    private function isHistoricalAccountCreation(): bool
    {
        return $this->isSystemMigration()
            && $this->action === 'CREATE'
            && $this->module === 'Users'
            && $this->auditable_type === User::class;
    }

    public function displayDescription(): string
    {
        if ($this->isHistoricalAccountCreation()) {
            return preg_replace(
                '/^Reconstructed creation entry for account (.+); original creator unavailable\.$/s',
                'User account for $1 created.',
                (string) $this->description,
            ) ?? (string) $this->description;
        }

        if ($this->action === 'UPDATE' && $this->module === 'Users') {
            $masked = preg_replace(
                '/ account for .+? \(Status:/',
                ' account (Status:',
                (string) $this->description
            );

            return is_string($masked) ? $masked : (string) $this->description;
        }

        return (string) $this->description;
    }
}
