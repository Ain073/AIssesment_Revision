<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use LogicException;

class AuditLogger
{
    /**
     * Record an audit log entry.
     *
     * Usage (from any Controller):
     *
     *   AuditLogger::log('PUBLISH', 'Assessments', "Published 'Midterm Exam' to class BSIT 3-A");
     *   AuditLogger::log('DELETE', 'Classes', "Archived class BSIT 2-B", $class);
     *   AuditLogger::log('LOGIN',  'Auth',    "User logged in");
     *
     * @param  string  $action  e.g. 'CREATE', 'UPDATE', 'DELETE', 'PUBLISH', 'GRADE', 'LOGIN', 'LOGOUT', 'DESIGNATE', 'REVOKE', 'ARCHIVE', 'APPROVE'
     * @param  string  $module  e.g. 'Assessments', 'Classes', 'Grading', 'Users', 'Designations', 'Auth', 'Subjects', 'Programs', 'Reports'
     * @param  string  $description  Human-readable sentence of what happened.
     * @param  Model|null  $model  Optional Eloquent model that was affected (for polymorphic reference).
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?Model $model = null,
        array $metadata = [],
    ): void {
        try {
            self::logOrFail($action, $module, $description, $model, $metadata);
        } catch (\Throwable $exception) {
            Log::error('Audit log could not be recorded.', [
                'action' => strtoupper($action),
                'module' => $module,
                'auditable_type' => $model ? get_class($model) : null,
                'auditable_id' => $model?->getKey(),
                'exception_type' => get_class($exception),
            ]);
            throw $exception;
        }
    }

    /**
     * Required audit writes must run inside the transaction for the action.
     * Let failures propagate so the caller rolls back instead of losing the log.
     */
    public static function logOrFail(
        string $action,
        string $module,
        string $description,
        ?Model $model = null,
        array $metadata = [],
    ): void {
        $recorder = app(AuditTrailRecorder::class);
        $user = $recorder->active() ? $recorder->actor() : Auth::user();
        $action = strtoupper($action);

        // Determine the user's highest-priority role for snapshot
        $userRole = null;
        if ($user) {
            $roles = $user->relationLoaded('roles')
                ? $user->roles
                : $user->roles()->get();

            $userRole = match (true) {
                $roles->contains('role_name', 'super_admin') => 'super_admin',
                $roles->contains('role_name', 'admin_dean') => 'admin_dean',
                $roles->contains('role_name', 'department_chair') => 'department_chair',
                $roles->contains('role_name', 'instructor') => 'instructor',
                $roles->contains('role_name', 'student') => 'student',
                default => null,
            };
        }

        if ($module === 'Users' && $action === 'CREATE'
            && (! $model instanceof User || ! $model->exists || $model->getKey() === null
                || $user?->getKey() === null || $userRole === null)) {
            throw new LogicException('User creation audit requires a saved account and a known actor with an assigned role.');
        }

        // Provenance comes from the server, never from caller metadata or a
        // client-controlled User-Agent that happens to match a backfill label.
        $metadata['event_source'] = AuditLog::APPLICATION_SOURCE;
        if ($user) {
            $metadata['actor'] = ['id' => $user->id, 'name' => $user->displayName(), 'email' => $user->email];
        }

        $event = AuditLog::create([
            'user_id' => $user?->id,
            'user_role' => $userRole,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
            'metadata' => $metadata,
        ]);
        $recorder->explicit($event);
    }
}
