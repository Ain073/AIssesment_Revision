<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Support\RecordSearch;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:50'],
        ]);
        $module = $filters['module'] ?? null;
        $action = $filters['action'] ?? null;

        $query = AuditLog::with('user')
            ->withoutSupersededUserCreations()
            ->orderByDesc('created_at')->orderByDesc('audit_log_id');

        // Options come from visible logs for the feature, independently of search/date filters.
        $actions = (clone $query)
            ->when($module, fn ($q) => $q->where('module', $module))
            ->whereNotNull('action')
            ->where('action', '!=', '')
            ->reorder('action')
            ->distinct()
            ->pluck('action');

        if ($action && ! $actions->contains($action)) {
            $action = null;
            $request->query->remove('action');
            $request->query->remove('page');
        }

        // -- Filters ---------------------------------------------------
        RecordSearch::apply($query, ['description', 'user.first_name', 'user.last_name', 'user.email', 'metadata->actor->name', 'metadata->actor->email']);

        if ($role = $request->input('role')) {
            $query->where('user_role', $role);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($module) {
            $query->where('module', $module);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->withQueryString();

        // Load only referenced accounts needed by generic old UPDATE descriptions.
        $logs->getCollection()->filter(fn (AuditLog $log) => $log->needsCurrentAccountLabel())
            ->load(['auditable' => fn (MorphTo $target) => $target->morphWith([
                User::class => ['studentProfile', 'instructorProfile'],
            ])]);

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

        return view('super-admin.audit-logs.index', compact(
            'logs', 'roles', 'actions', 'modules'
        ));
    }
}
