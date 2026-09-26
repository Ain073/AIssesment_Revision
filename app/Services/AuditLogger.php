<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

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
     * @param  string      $action      e.g. 'CREATE', 'UPDATE', 'DELETE', 'PUBLISH', 'GRADE', 'LOGIN', 'LOGOUT', 'DESIGNATE', 'REVOKE', 'ARCHIVE', 'APPROVE'
     * @param  string      $module      e.g. 'Assessments', 'Classes', 'Grading', 'Users', 'Designations', 'Auth', 'Subjects', 'Programs', 'Reports'
     * @param  string      $description Human-readable sentence of what happened.
     * @param  Model|null  $model       Optional Eloquent model that was affected (for polymorphic reference).
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?Model $model = null,
    ): void {
        try {
            $user = Auth::user();

            // Determine the user's highest-priority role for snapshot
            $userRole = null;
            if ($user) {
                $roles = $user->relationLoaded('roles')
                    ? $user->roles
                    : $user->roles()->get();

                $userRole = match (true) {
                    $roles->contains('role_name', 'super_admin')      => 'super_admin',
                    $roles->contains('role_name', 'admin_dean')       => 'admin_dean',
                    $roles->contains('role_name', 'department_chair') => 'department_chair',
                    $roles->contains('role_name', 'instructor')       => 'instructor',
                    $roles->contains('role_name', 'student')          => 'student',
                    default                                            => null,
                };
            }

            AuditLog::create([
                'user_id'        => $user?->id,
                'user_role'      => $userRole,
                'action'         => strtoupper($action),
                'module'         => $module,
                'description'    => $description,
                'auditable_type' => $model ? get_class($model) : null,
                'auditable_id'   => $model?->getKey(),
                'ip_address'     => Request::ip(),
                'user_agent'     => Request::userAgent(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Silently fail — audit logging must never break the main app flow
        }
    }
}
