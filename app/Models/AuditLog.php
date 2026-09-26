<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Human-readable label for the user_role field.
     */
    public function userRoleLabel(): string
    {
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
        return $this->user?->displayName() ?? 'Deleted Account';
    }
}
