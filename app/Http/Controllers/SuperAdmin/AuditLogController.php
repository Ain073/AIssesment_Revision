<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')
            ->orderByDesc('created_at');

        // -- Filters ---------------------------------------------------
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$search}%")
                                                     ->orWhere('last_name', 'like', "%{$search}%")
                                                     ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($role = $request->input('role')) {
            $query->where('user_role', $role);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->withQueryString();

        // Predefined system roles map
        $roles = [
            'super_admin'      => 'Admin',
            'admin_dean'       => 'Dean',
            'department_chair' => 'Dept. Chair',
            'instructor'       => 'Instructor',
            'student'          => 'Student',
        ];

        // Comprehensive modules list
        $modules = collect([
            'Auth',
            'Users',
            'Colleges',
            'Programs',
            'Designations',
            'Classes',
            'Assessments',
            'Grading',
            'Passing Rates',
            'Reports',
            'Subjects',
        ])
            ->merge(AuditLog::distinct()->pluck('module')->filter())
            ->unique()
            ->sort()
            ->values();

        // Comprehensive actions list
        $actions = collect([
            'LOGIN',
            'LOGOUT',
            'CREATE',
            'UPDATE',
            'DELETE',
            'ARCHIVE',
            'RESTORE',
            'PUBLISH',
            'SUBMIT',
            'GRADE',
            'DESIGNATE',
            'REVOKE',
            'ENROLL',
            'APPROVE',
            'REJECT',
            'IMPORT',
            'FINALIZE',
            'GENERATE',
            'REOPEN',
            'ADD_ITEM',
        ])
            ->merge(AuditLog::distinct()->pluck('action')->filter())
            ->unique()
            ->sort()
            ->values();

        return view('super-admin.audit-logs.index', compact(
            'logs', 'roles', 'actions', 'modules'
        ));
    }
}
