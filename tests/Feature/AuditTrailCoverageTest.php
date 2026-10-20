<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ClassDetail;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditTrailCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'hashing.bcrypt.rounds' => 4]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach ([
            'users' => 'id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, name TEXT, first_name TEXT, middle_name TEXT, last_name TEXT, email TEXT UNIQUE, password TEXT, status TEXT, must_change_password INTEGER DEFAULT 0, session_version INTEGER DEFAULT 0, remember_token TEXT, profile_photo_path TEXT, profile_photo_mime TEXT, profile_photo_data TEXT, created_at DATETIME, updated_at DATETIME',
            'colleges' => 'college_id INTEGER PRIMARY KEY, public_id TEXT, college_name TEXT, acronym TEXT, created_at DATETIME, updated_at DATETIME',
            'departments' => 'department_id INTEGER PRIMARY KEY, public_id TEXT, college_id INTEGER, dept_name TEXT, created_at DATETIME, updated_at DATETIME',
            'programs' => 'program_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, department_id INTEGER, program_name TEXT, is_active INTEGER DEFAULT 1, created_at DATETIME, updated_at DATETIME',
            'instructor_profiles' => 'instructor_profile_id INTEGER PRIMARY KEY, user_id INTEGER, department_id INTEGER, employee_number TEXT, created_at DATETIME, updated_at DATETIME',
            'student_profiles' => 'student_profile_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, user_id INTEGER, program_id INTEGER, student_number TEXT, created_at DATETIME, updated_at DATETIME',
            'subjects' => 'subject_id INTEGER PRIMARY KEY, public_id TEXT, department_id INTEGER, subject_code TEXT UNIQUE, subject_name TEXT, is_active INTEGER DEFAULT 1, created_at DATETIME, updated_at DATETIME',
            'semesters' => 'semester_id INTEGER PRIMARY KEY, semester_name TEXT, is_active INTEGER, created_at DATETIME, updated_at DATETIME',
            'classes' => 'class_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, program_id INTEGER, semester_id INTEGER, section_name TEXT, year_level INTEGER, school_year TEXT, join_code TEXT, join_token TEXT, archived_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'class_details' => 'class_details_id INTEGER PRIMARY KEY, class_id INTEGER, instructor_id INTEGER, student_id INTEGER, subject_id INTEGER, status TEXT, entry_method TEXT, created_at DATETIME, updated_at DATETIME',
            'assessments' => 'assessment_id INTEGER PRIMARY KEY, public_id TEXT, subject_id INTEGER, instructor_id INTEGER, title TEXT, created_at DATETIME, updated_at DATETIME',
            'app_notifications' => 'notification_id INTEGER PRIMARY KEY, public_id TEXT, user_id INTEGER, title TEXT, message TEXT, type TEXT, link TEXT, read_at DATETIME, created_at DATETIME, updated_at DATETIME',
        ] as $table => $columns) {
            DB::statement("CREATE TABLE {$table} ({$columns})");
        }
        $this->createAuditTables();
        DB::table('colleges')->insert(['college_id' => 1, 'public_id' => (string) Str::uuid(), 'college_name' => 'College', 'acronym' => 'COL']);
        DB::table('departments')->insert(['department_id' => 1, 'public_id' => (string) Str::uuid(), 'college_id' => 1, 'dept_name' => 'Department']);
        DB::table('programs')->insert(['program_id' => 1, 'public_id' => (string) Str::uuid(), 'department_id' => 1, 'program_name' => 'Program']);
        DB::table('semesters')->insert(['semester_id' => 1, 'semester_name' => 'First Semester', 'is_active' => 1]);
    }

    public function test_subject_crud_records_actor_before_after_and_survives_deletion(): void
    {
        $actor = $this->account('department_chair');
        $this->signIn($actor)->postJson(route('department-chair.subjects.store'), [
            'subject_code' => 'AUD-101', 'subject_name' => 'Original subject', 'is_active' => true,
        ])->assertOk();
        $subject = Subject::where('subject_code', 'AUD-101')->sole();
        $this->putJson(route('department-chair.subjects.update', $subject), [
            'subject_code' => 'AUD-101', 'subject_name' => 'Updated subject', 'is_active' => false,
        ])->assertOk();
        $event = AuditLog::where('module', 'Subjects')->where('action', 'UPDATE')->sole();
        $change = collect($event->metadata['changes'])->firstWhere('model', Subject::class);
        $this->assertSame($actor->id, $event->user_id);
        $this->assertSame('department_chair', $event->user_role);
        $this->assertSame('Original subject', $change['before']['subject_name']);
        $this->assertSame('Updated subject', $change['after']['subject_name']);
        $this->deleteJson(route('department-chair.subjects.destroy', $subject))->assertOk();
        $this->assertDatabaseMissing('subjects', ['subject_id' => $subject->getKey()]);
        $this->assertSame(['CREATE', 'UPDATE', 'DELETE'], AuditLog::orderBy('audit_log_id')->pluck('action')->all());

        $this->signIn($this->account('super_admin'))->get(route('super-admin.audit-logs', ['module' => 'Subjects', 'role' => 'department_chair']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 3)
            ->assertSee($actor->email)->assertSee('Updated subject');
    }

    public function test_program_crud_and_dean_department_creation_have_audit_entries(): void
    {
        $this->signIn($this->account('department_chair'))->post(route('department-chair.programs.store'), ['program_name' => 'Audit program', 'is_active' => true])->assertRedirect();
        $program = Program::where('program_name', 'Audit program')->sole();
        $this->put(route('department-chair.programs.update', $program), ['program_name' => 'Renamed program', 'is_active' => false])->assertRedirect();
        $this->delete(route('department-chair.programs.destroy', $program))->assertRedirect();
        $this->assertSame(['CREATE', 'UPDATE', 'DELETE'], AuditLog::where('module', 'Programs')->orderBy('audit_log_id')->pluck('action')->all());
        $dean = $this->account('admin_dean');
        $this->signIn($dean)->post(route('admin-dean.departments.store'), ['dept_name' => 'New department'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['module' => 'Colleges', 'action' => 'CREATE', 'user_id' => $dean->id]);
    }

    public static function accountPortals(): array
    {
        return [['super-admin', 'super_admin'], ['admin-dean', 'admin_dean'], ['department-chair', 'department_chair']];
    }

    #[DataProvider('accountPortals')]
    public function test_user_edits_and_deletion_are_logged_once_without_passwords(string $portal, string $role): void
    {
        $actor = $this->account($role);
        $target = $this->account('student');
        $passwordHash = $target->password;
        $this->signIn($actor)->putJson(route("{$portal}.users.update", $target), $this->userPayload($target))->assertOk();
        $event = AuditLog::where('action', 'UPDATE')->sole();
        $this->assertSame($actor->id, $event->user_id);
        $this->assertSame($target->id, $event->auditable_id);
        $change = collect($event->metadata['changes'])->firstWhere('model', User::class);
        $this->assertSame('Updated', $change['after']['first_name']);
        $this->assertArrayNotHasKey('password', $change['before']);
        $this->assertStringNotContainsString($passwordHash, json_encode($event->metadata));
        $this->delete(route("{$portal}.users.destroy", $target))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertSame(2, AuditLog::where('module', 'Users')->count());
        $this->assertSame($actor->id, AuditLog::where('action', 'DELETE')->sole()->user_id);
    }

    #[DataProvider('accountPortals')]
    public function test_failed_audit_rolls_back_user_profile_and_role_updates(string $portal, string $role): void
    {
        $actor = $this->account($role);
        $target = $this->account('student');
        $before = $this->userState();
        DB::statement("CREATE TRIGGER reject_required_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Required audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->signIn($actor)->putJson(route("{$portal}.users.update", $target), $this->userPayload($target));
            $this->fail('A successful update must include its audit entry.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Required audit failure', $exception->getMessage());
        }
        $this->assertSame($before, $this->userState());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_class_import_removal_and_student_join_requests_record_the_correct_actors(): void
    {
        $teacher = $this->account('instructor');
        $first = $this->account('student');
        $second = $this->account('student');
        $class = $this->classFor($teacher);
        $token = (string) Str::uuid();
        $this->signIn($teacher)->withSession(["class_student_imports.{$token}" => [
            'class_id' => $class->getKey(), 'student_profile_ids' => [$first->studentProfile->getKey(), $second->studentProfile->getKey()],
        ]])->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token])->assertRedirect();
        $imports = AuditLog::where('action', 'ENROLL')->get();
        $this->assertCount(2, $imports);
        foreach ($imports as $import) {
            $this->assertSame($teacher->id, $import->user_id);
            $this->assertCount(1, $import->metadata['changes']);
            $this->assertSame(ClassDetail::class, $import->auditable_type);
        }
        $this->deleteJson(route('instructor.classes.students.destroy', [$class, $first->studentProfile]))->assertOk();
        $this->assertSame($teacher->id, AuditLog::where('action', 'UNENROLL')->sole()->user_id);
        $this->assertFalse($class->hasStudent($first->studentProfile));

        $this->signIn($first)->postJson(route('student.classes.join-code.request'), ['join_code' => $class->join_code])->assertOk();
        $join = AuditLog::where('action', 'REQUEST_JOIN')->sole();
        $this->assertSame($first->id, $join->user_id);
        $this->assertSame('student', $join->user_role);
        $this->postJson(route('student.classes.join.request', $class->join_token))->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'REQUEST_JOIN')->count());
    }

    public function test_a_caught_nested_rollback_does_not_create_a_false_audit_entry(): void
    {
        Route::middleware('web')->post('/audit-tests/rollback', function () {
            try {
                DB::transaction(function (): void {
                    Program::findOrFail(1)->update(['program_name' => 'Rolled back']);
                    throw new \RuntimeException('Simulated operation failure');
                });
            } catch (\RuntimeException) {
            }

            return response()->json(['ok' => true]);
        });
        $this->signIn($this->account('department_chair'))->postJson('/audit-tests/rollback')->assertOk();
        $this->assertSame('Program', Program::findOrFail(1)->program_name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_historical_instructor_events_display_their_saved_actor_and_role(): void
    {
        $teacher = $this->account('instructor');
        foreach ([
            ['Classes', 'CREATE', AcademicClass::class], ['Assessments', 'CREATE', Assessment::class],
            ['Assessments', 'PUBLISH', PublishAssessment::class], ['Reports', 'FINALIZE', Report::class],
        ] as [$module, $action, $type]) {
            $event = AuditLog::create(['module' => $module, 'action' => $action, 'description' => 'Historical event', 'user_id' => $teacher->id,
                'user_role' => 'instructor', 'auditable_type' => $type, 'auditable_id' => 10, 'user_agent' => 'System Migration', 'created_at' => now()]);
            $this->assertSame($teacher->displayName(), $event->actorName());
            $this->assertSame($teacher->email, $event->actorSubtitle());
            $this->assertSame('Instructor', $event->userRoleLabel());
        }
    }

    public function test_actor_identity_remains_searchable_after_rename_and_account_deletion(): void
    {
        $actor = $this->account('department_chair');
        $name = $actor->displayName();
        $email = $actor->email;
        $this->signIn($actor)->postJson(route('department-chair.subjects.store'), [
            'subject_code' => 'SNAP', 'subject_name' => 'Identity snapshot', 'is_active' => true,
        ])->assertOk();
        $actor->update(['first_name' => 'Renamed', 'email' => 'renamed@example.test']);
        $this->signIn($this->account('super_admin'));
        $actor->delete();

        $this->get(route('super-admin.audit-logs', ['search' => $name]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 1)
            ->assertSee($name)->assertSee($email)->assertDontSee('renamed@example.test');
    }

    public function test_request_throttling_survives_a_rolled_back_validation_response(): void
    {
        (require database_path('migrations/0001_01_01_000001_create_cache_table.php'))->up();
        config(['cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite']);
        $this->app['cache']->forgetDriver('database');
        $this->app->forgetInstance(RateLimiter::class);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(RateLimiter::class);
        Route::middleware(['web', 'throttle:2,1'])->post('/audit-tests/throttle', fn () => response()->json(['message' => 'Invalid'], 422));

        $this->postJson('/audit-tests/throttle')->assertUnprocessable();
        $this->postJson('/audit-tests/throttle')->assertUnprocessable();
        $this->postJson('/audit-tests/throttle')->assertTooManyRequests();
    }

    public function test_login_is_not_kept_when_its_required_audit_write_fails(): void
    {
        $user = $this->account('instructor');
        DB::statement("CREATE TRIGGER reject_login_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Login audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('login.submit'), ['email' => $user->email, 'password' => 'secret-password']);
            $this->fail('Authentication must not succeed without its required audit entry.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Login audit failure', $exception->getMessage());
        }
        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failed_audit_metadata_write_rolls_back_the_record_and_its_new_event(): void
    {
        $actor = $this->account('super_admin');
        $target = $this->account('student');
        $before = $this->userState();
        DB::statement("CREATE TRIGGER reject_audit_metadata BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Metadata audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->signIn($actor)->putJson(route('super-admin.users.update', $target), $this->userPayload($target));
            $this->fail('Audit details and the business record must commit together.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Metadata audit failure', $exception->getMessage());
        }
        $this->assertSame($before, $this->userState());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function signIn(User $user): static
    {
        return $this->withSession([AccountSessionService::VERSION_KEY => (int) $user->session_version,
            'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->getAuthPassword())])->actingAs($user);
    }

    private function account(string $role): User
    {
        $number = User::count() + 1;
        $user = User::create(['name' => "Actor {$number}", 'first_name' => "Actor{$number}", 'last_name' => 'User', 'email' => "actor{$number}@example.test", 'password' => 'secret-password', 'status' => 'active', 'must_change_password' => false]);
        foreach (in_array($role, ['admin_dean', 'department_chair']) ? ['instructor', $role] : [$role] as $name) {
            $user->roles()->attach(Role::firstOrCreate(['role_name' => $name])->getKey());
        }
        if (in_array($role, ['instructor', 'admin_dean', 'department_chair'])) {
            InstructorProfile::create(['user_id' => $user->id, 'department_id' => 1, 'employee_number' => "EMP-{$number}"]);
        } elseif ($role === 'student') {
            StudentProfile::create(['user_id' => $user->id, 'program_id' => 1, 'student_number' => "STU-{$number}"]);
        }

        return $user;
    }

    private function userPayload(User $target): array
    {
        return ['first_name' => 'Updated', 'last_name' => 'Student', 'email' => $target->email, 'status' => 'active', 'base_role' => 'student',
            'program_id' => 1, 'student_number' => $target->studentProfile->student_number];
    }

    private function userState(): array
    {
        return array_map(fn ($table) => DB::table($table)->orderBy($table === 'users' ? 'id' : ($table === 'user_roles' ? 'user_id' : 'student_profile_id'))->get()->toJson(), ['users', 'student_profiles', 'user_roles']);
    }

    private function classFor(User $teacher): AcademicClass
    {
        $subject = Subject::create(['department_id' => 1, 'subject_code' => 'AUDIT', 'subject_name' => 'Audit subject', 'is_active' => true]);
        $class = AcademicClass::create(['program_id' => 1, 'semester_id' => 1, 'section_name' => 'BSIT 1-A', 'year_level' => 1,
            'join_code' => 'AUDIT1', 'join_token' => Str::random(40)]);
        $class->syncContext($teacher->instructorProfile, $subject->getKey());

        return $class;
    }
}
