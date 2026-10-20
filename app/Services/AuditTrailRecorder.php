<?php

namespace App\Services;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\AssessmentItemChoice;
use App\Models\AuditLog;
use App\Models\ClassDetail;
use App\Models\College;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DesignationDetail;
use App\Models\InstructorProfile;
use App\Models\PassingRateSetting;
use App\Models\Program;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\SubmissionSecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Collect committed domain changes for one HTTP operation. */
class AuditTrailRecorder
{
    public const MODELS = [
        User::class => 'Users', InstructorProfile::class => 'Users', StudentProfile::class => 'Users',
        College::class => 'Colleges', Department::class => 'Colleges', Program::class => 'Programs',
        Subject::class => 'Subjects', Semester::class => 'Subjects',
        AcademicClass::class => 'Classes', ClassDetail::class => 'Classes',
        Assessment::class => 'Assessments', AssessmentItem::class => 'Assessments',
        AssessmentItemChoice::class => 'Assessments', PublishAssessment::class => 'Assessments',
        Submission::class => 'Assessments', SubmissionAnswer::class => 'Assessments',
        SubmissionSecurityEvent::class => 'Assessments', Report::class => 'Reports',
        PassingRateSetting::class => 'Passing Rates', Designation::class => 'Designations',
        DesignationDetail::class => 'Designations',
    ];

    private bool $active = false;

    private ?Request $request = null;

    private ?User $actor = null;

    private array $pending = [];

    private array $explicitEvents = [];

    private array $students = [];

    private array $classes = [];

    public function active(): bool
    {
        return $this->active;
    }

    public function start(Request $request): void
    {
        $this->reset();
        $this->active = true;
        $this->request = $request;
        $this->actor = $request->user();
    }

    public function actor(): ?User
    {
        return $this->actor ?? $this->request?->user();
    }

    public function verifiedPasswordResetActor(User $user): void
    {
        if ($this->active && $this->request?->routeIs('password.update')) {
            $this->actor = $user;
        }
    }

    public function remember(Model $model): void
    {
        if (! $this->active || ! isset(self::MODELS[$model::class])) {
            return;
        }

        $key = $model::class.':'.($model->getKey() ?? 'new-'.spl_object_id($model));
        if (! isset($this->pending[$key])) {
            $before = $model->exists ? $this->safe($model->getRawOriginal()) : null;
            if ($model instanceof User && $model->exists) {
                $before['roles'] = $model->roles()->orderBy('role_name')->pluck('role_name')->all();
                $before['profiles'] = $this->profiles($model);
            }
            if ($model instanceof AssessmentItem && $model->exists) {
                $before['choices'] = $model->choices()->orderBy('assessment_item_choice_id')->get()->map(fn ($choice) => $this->safe($choice->getRawOriginal()))->all();
            }
            $this->pending[$key] = [
                'model' => $model, 'before' => $before, 'flags' => [],
                'sensitive_before' => array_intersect_key($model->getRawOriginal(), array_flip(['password', 'profile_photo_data'])),
            ];
        }

        foreach (['password', 'profile_photo_data'] as $field) {
            if ($model->isDirty($field)) {
                $this->pending[$key]['flags'][$field === 'password' ? 'password_changed' : 'photo_changed'] = true;
            }
        }
    }

    public function explicit(AuditLog $event): void
    {
        if ($this->active) {
            $this->explicitEvents[$event->getKey()] = $event;
        }
    }

    public function rememberDesignations(User $user): void
    {
        if (! $this->active) {
            return;
        }
        $this->remember($user);
        $profile = $user->instructorProfile;
        $collegeId = $profile?->department?->college_id;
        $details = DesignationDetail::where('status', 'active')
            ->where(function ($query) use ($profile, $collegeId): void {
                $query->where('instructor_id', $profile?->getKey() ?? 0);
                if ($collegeId) {
                    $query->orWhere('college_id', $collegeId);
                }
            })->with('instructor.user')->get();
        foreach ($details as $detail) {
            $this->remember($detail);
            if ($detail->instructor?->user) {
                $this->remember($detail->instructor->user);
            }
        }
    }

    public function flush(): void
    {
        // Re-read the final state: a caught nested-transaction failure must not
        // produce a log for changes that were already rolled back.
        $pending = [];
        foreach ($this->pending as $entry) {
            $key = $entry['model']::class.':'.$entry['model']->getKey();
            if (isset($pending[$key])) {
                $pending[$key]['flags'] += $entry['flags'];
            } else {
                $pending[$key] = $entry;
            }
        }
        $current = [];
        foreach (collect($pending)->groupBy(fn ($entry) => $entry['model']::class) as $type => $entries) {
            $query = (new $type)->newQuery()->whereKey($entries->map(fn ($entry) => $entry['model']->getKey())->all());
            if ($type === User::class) {
                $query->with(['roles', 'instructorProfile', 'studentProfile']);
            } elseif ($type === AssessmentItem::class) {
                $query->with('choices');
            }
            $current[$type] = $query->get()->keyBy(fn ($record) => $record->getKey());
        }

        $groups = [];
        foreach ($pending as $entry) {
            $model = $entry['model'];
            $fresh = $current[$model::class]->get($model->getKey());
            $after = $fresh ? $this->safe($fresh->getRawOriginal()) : null;
            if ($fresh instanceof User) {
                $after['roles'] = $fresh->roles->sortBy('role_name')->pluck('role_name')->values()->all();
                $after['profiles'] = $this->profiles($fresh);
            }
            if ($fresh instanceof AssessmentItem) {
                $after['choices'] = $fresh->choices->sortBy('assessment_item_choice_id')->map(fn ($choice) => $this->safe($choice->getRawOriginal()))->values()->all();
            }
            foreach (['password' => 'password_changed', 'profile_photo_data' => 'photo_changed'] as $field => $flag) {
                if (($entry['sensitive_before'][$field] ?? null) === $fresh?->getRawOriginal($field)) {
                    unset($entry['flags'][$flag]);
                }
            }
            if ($entry['before'] === $after && $entry['flags'] === []) {
                continue;
            }
            if ($entry['before'] === null && $after === null) {
                continue;
            }

            $module = $this->module($model);
            $groups[$module][] = [
                'model' => $model::class, 'id' => $model->getKey(),
                'before' => $entry['before'], 'after' => $after,
                ...$entry['flags'],
            ];
        }

        $present = array_fill_keys(AuditLog::whereKey(array_keys($this->explicitEvents))->pluck('audit_log_id')->all(), true);
        $userChanges = [];
        foreach ($groups['Users'] ?? [] as $change) {
            $id = $change['model'] === User::class ? $change['id'] : ($change['after']['user_id'] ?? $change['before']['user_id'] ?? 0);
            $userChanges[$id][] = $change;
        }
        foreach ($this->explicitEvents as $id => $event) {
            if (! isset($present[$id])) {
                continue;
            }
            $metadata = $event->metadata ?? [];
            $changes = $groups[$event->module] ?? [];
            if ($event->module === 'Users' && $event->auditable_type === User::class) {
                $changes = $userChanges[$event->auditable_id] ?? [];
            }
            $metadata['changes'] = array_merge($metadata['changes'] ?? [], $changes);
            AuditLog::whereKey($id)->update(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);
        }

        $covered = collect($this->explicitEvents)->filter(fn ($event) => isset($present[$event->getKey()]))
            ->pluck('module')->all();
        foreach ($groups as $module => $changes) {
            if (in_array($module, $covered, true)) {
                continue;
            }
            // Context-only roster rows belong to class creation/editing, rather
            // than a separate student enrollment event.
            $meaningful = array_values(array_filter($changes, fn ($change) => $change['model'] !== ClassDetail::class
                || ($change['after']['student_id'] ?? $change['before']['student_id'] ?? null) !== null));
            if ($meaningful === []) {
                continue;
            }

            if ($module === 'Classes') {
                $details = collect($meaningful)->where('model', ClassDetail::class)
                    ->map(fn ($change) => $change['after'] ?? $change['before']);
                $this->students = StudentProfile::with('user')->whereKey($details->pluck('student_id')->unique())->get()->keyBy('student_profile_id')->all();
                $this->classes = AcademicClass::whereKey($details->pluck('class_id')->unique())->get()->keyBy('class_id')->all();
            }

            // Imported students each need a searchable enrollment entry and
            // stable roster reference, just like manually enrolled students.
            $batches = $this->request?->routeIs('instructor.classes.students.import.confirm')
                ? array_map(fn ($change) => [$change], $meaningful) : [$meaningful];
            foreach ($batches as $batch) {
                [$action, $description, $target] = $this->describe($module, $batch);
                AuditLogger::logOrFail($action, $module, $description, $target, ['changes' => count($batches) > 1 ? $batch : $changes]);
            }
        }
    }

    public function reset(): void
    {
        $this->active = false;
        $this->request = null;
        $this->actor = null;
        $this->pending = $this->explicitEvents = $this->students = $this->classes = [];
    }

    private function module(Model $model): string
    {
        if ($this->request?->routeIs('super-admin.roles.*', 'admin-dean.teachers.department-chair.*')) {
            return 'Designations';
        }
        if ($this->request?->routeIs('password.update', 'password.setup.update', 'profile.password.update')) {
            return 'Auth';
        }
        if ($this->request?->routeIs('profile.photo.update')) {
            return 'Profile';
        }
        if ($this->request?->routeIs('instructor.assessments.submissions.grade.update')) {
            return 'Grading';
        }

        return self::MODELS[$model::class];
    }

    private function describe(string $module, array $changes): array
    {
        $change = collect($changes)->firstWhere('model', User::class) ?? $changes[0];
        $type = $change['model'];
        $target = new $type;
        $target->setRawAttributes(($change['after'] ?? $change['before']) + [$target->getKeyName() => $change['id']], true);
        $action = $change['before'] === null ? 'CREATE' : ($change['after'] === null ? 'DELETE' : 'UPDATE');
        $state = $change['after'] ?? $change['before'];
        $label = $state['title'] ?? $state['subject_name'] ?? $state['program_name'] ?? $state['dept_name']
            ?? $state['college_name'] ?? $state['semester_name'] ?? $state['section_name'] ?? '#'.$change['id'];

        if ($target instanceof User) {
            $label = $target->displayName();
        } elseif ($target instanceof StudentProfile || $target instanceof InstructorProfile) {
            $target = User::find($state['user_id']) ?? $target;
            $label = $target instanceof User ? $target->displayName() : $label;
        }

        $routes = [
            'password.update' => ['PASSWORD_RESET', 'Reset account password'],
            'password.setup.update' => ['PASSWORD_SETUP', 'Completed first-login password setup'],
            'profile.password.update' => ['PASSWORD_CHANGE', 'Changed account password'],
            'profile.photo.update' => ['UPDATE', 'Updated profile picture'],
            'department-chair.subjects.semester.activate' => ['ACTIVATE', "Activated semester '{$label}'"],
            'instructor.reports.autosave' => ['AUTOSAVE', 'Autosaved report draft'],
            'instructor.classes.students.import.confirm' => ['ENROLL', 'Enrolled student'],
            'instructor.classes.students.destroy' => ['UNENROLL', 'Removed student from class'],
            'student.classes.join.request' => ['REQUEST_JOIN', 'Requested to join class'],
            'student.classes.join-code.request' => ['REQUEST_JOIN', 'Requested to join class'],
            'student.assessments.security-events.store' => ['SECURITY_EVENT', 'Recorded assessment security event'],
        ];
        foreach ($routes as $route => [$routeAction, $description]) {
            if ($this->request?->routeIs($route)) {
                if ($target instanceof ClassDetail) {
                    $student = $this->students[$state['student_id']] ?? null;
                    $class = $this->classes[$state['class_id']] ?? null;
                    $studentLabel = ($student?->user?->displayName() ?? 'Student #'.$state['student_id'])
                        .' ('.($student?->student_number ?? $state['student_id']).')';
                    $classLabel = $class?->class_name ?? 'Class #'.$state['class_id'];
                    $description = $routeAction === 'ENROLL' ? "Enrolled student {$studentLabel} into {$classLabel}"
                        : "{$description} — {$studentLabel} in {$classLabel}";
                }

                return [$routeAction, $description, $target];
            }
        }

        $verb = ['CREATE' => 'Created', 'UPDATE' => 'Updated', 'DELETE' => 'Deleted'][$action];

        $noun = match (true) {
            $target instanceof User => 'user account', $target instanceof Subject => 'subject',
            $target instanceof Program => 'program', $target instanceof Department => 'department',
            $target instanceof College => 'college', $target instanceof AcademicClass => 'class',
            $target instanceof Assessment => 'assessment', $target instanceof AssessmentItem => 'question',
            $target instanceof Report => 'report', default => strtolower($module).' record',
        };

        return [$action, "{$verb} {$noun} '{$label}'", $target];
    }

    private function profiles(User $user): array
    {
        return [
            'instructor' => $this->safe(($user->relationLoaded('instructorProfile') ? $user->instructorProfile : InstructorProfile::where('user_id', $user->getKey())->first())?->getRawOriginal() ?? []),
            'student' => $this->safe(($user->relationLoaded('studentProfile') ? $user->studentProfile : StudentProfile::where('user_id', $user->getKey())->first())?->getRawOriginal() ?? []),
        ];
    }

    private function safe(array $attributes): array
    {
        $attributes = array_filter($attributes, fn ($key) => ! in_array($key, [
            'password', 'remember_token', 'profile_photo_data', 'created_at', 'updated_at',
        ], true) && ! str_contains(strtolower($key), 'token'), ARRAY_FILTER_USE_KEY);

        return array_map(fn ($value) => is_bool($value) ? (int) $value : $value, $attributes);
    }
}
