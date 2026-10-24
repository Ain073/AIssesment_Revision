<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\AuditLog;
use App\Models\ClassDetail;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\SubmissionSecurityEvent;
use App\Models\Subject;
use App\Models\User;
use App\Services\AccountSessionService;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
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
            'class_details' => 'class_details_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, class_id INTEGER, instructor_id INTEGER, student_id INTEGER, subject_id INTEGER, status TEXT, entry_method TEXT, created_at DATETIME, updated_at DATETIME',
            'assessments' => 'assessment_id INTEGER PRIMARY KEY, public_id TEXT, subject_id INTEGER, instructor_id INTEGER, title TEXT, status TEXT, created_at DATETIME, updated_at DATETIME',
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

    public static function classRosterFormats(): array
    {
        return ['csv' => ['csv', '23-SC-4217'], 'xlsx' => ['xlsx', '23-Sc-4217']];
    }

    #[DataProvider('classRosterFormats')]
    public function test_class_import_matches_student_numbers_without_case_and_enrolls_each_student_once(string $format, string $storedNumber): void
    {
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $student->studentProfile->update(['student_number' => $storedNumber]);
        $class = $this->classFor($teacher);
        $before = $this->userState();
        $response = $this->signIn($teacher)->post(route('instructor.classes.students.import.preview', $class), [
            'student_file' => $this->classRosterFile($format, [' 23-sc-4217 ', '23-SC-4217', '23-sC-4217', '23-sc-missing']),
        ])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $token = $query['import_token'];
        $preview = session("class_student_imports.{$token}");
        $this->assertSame(['total' => 2, 'ready' => 1, 'already_enrolled' => 0, 'inactive' => 0, 'not_found' => 1], $preview['summary']);
        $this->assertSame([$student->studentProfile->getKey()], $preview['student_profile_ids']);
        $this->assertSame('23-sc-4217', $preview['rows'][0]['student_number']);
        $this->assertSame('Ready', $preview['rows'][0]['status_badge']);
        $this->assertSame($before, $this->userState());
        $this->assertFalse($class->hasStudent($student->studentProfile));

        $this->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $class->classDetails()->where('student_id', $student->studentProfile->getKey())->count());
        $this->assertSame($before, $this->userState());
        $this->assertSame($teacher->id, AuditLog::where('action', 'ENROLL')->sole()->user_id);
    }

    public function test_class_import_rejects_txt_uploads_without_changing_accounts_or_enrollments(): void
    {
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $class = $this->classFor($teacher);
        $before = $this->userState();

        $this->signIn($teacher)->post(route('instructor.classes.students.import.preview', $class), [
            'student_file' => $this->classRosterFile('txt', [$student->studentProfile->student_number]),
        ])->assertRedirect()->assertSessionHasErrors('student_file')->assertSessionMissing('class_student_imports');

        $this->assertSame($before, $this->userState());
        $this->assertFalse($class->hasStudent($student->studentProfile));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_case_insensitive_class_import_keeps_inactive_and_already_enrolled_checks(): void
    {
        $teacher = $this->account('instructor');
        $enrolled = $this->account('student');
        $inactive = $this->account('student');
        $enrolled->studentProfile->update(['student_number' => '23-SC-1001']);
        $inactive->studentProfile->update(['student_number' => '23-SC-1002']);
        $inactive->update(['status' => 'inactive']);
        StudentProfile::create(['user_id' => $teacher->id, 'program_id' => 1, 'student_number' => '23-SC-1003']);
        $class = $this->classFor($teacher);
        $class->addStudentWithMethod($enrolled->studentProfile, ClassDetail::METHOD_MANUAL_ADD);
        $before = $this->userState();
        $response = $this->signIn($teacher)->post(route('instructor.classes.students.import.preview', $class), [
            'student_file' => $this->classRosterFile('csv', ['23-sc-1001', '23-sc-1002', '23-sc-1003']),
        ])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $preview = session('class_student_imports.'.$query['import_token']);
        $this->assertSame(['total' => 3, 'ready' => 0, 'already_enrolled' => 1, 'inactive' => 1, 'not_found' => 1], $preview['summary']);
        $this->assertSame([], $preview['student_profile_ids']);
        $this->assertSame($before, $this->userState());
    }

    public function test_class_import_exclusions_preserve_accounts_and_existing_enrollments(): void
    {
        $teacher = $this->account('instructor');
        $kept = $this->account('student');
        $extra = $this->account('student');
        $already = $this->account('student');
        $inactive = $this->account('student');
        $inactive->update(['status' => 'inactive']);
        $class = $this->classFor($teacher);
        $class->addStudentWithMethod($already->studentProfile, ClassDetail::METHOD_MANUAL_ADD);
        $existingDetails = $class->classDetails()->where('student_id', $already->studentProfile->getKey())->get()->toJson();
        $this->signIn($teacher);
        [$token, $preview] = $this->previewClassRoster($class, [
            $kept->studentProfile->student_number, $extra->studentProfile->student_number,
            $already->studentProfile->student_number, $inactive->studentProfile->student_number, 'MISSING-STUDENT',
        ]);
        $before = $this->userState();
        $this->assertSame(2, $preview['summary']['ready']);
        $this->assertSame($extra->studentProfile->getKey(), $preview['rows'][1]['student_profile_id']);

        $html = view('instructor.classes.import-preview-popup', ['class' => $class, 'importPreview' => ['token' => $token, ...$preview]])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@data-class-roster-import]')->length);
        $this->assertSame(5, $xpath->query('//form[@data-class-roster-import]//input[@name="excluded_rows[]" and @disabled]')->length);
        $this->assertSame(5, $xpath->query('//form[@data-class-roster-import]//button[@data-roster-toggle and @type="button"]')->length);
        $this->assertSame($before, $this->userState());
        $this->assertFalse($class->hasStudent($kept->studentProfile));

        $this->post(route('instructor.classes.students.import.confirm', $class), [
            'import_token' => $token, 'excluded_rows' => ['1', '2', '3', '4'],
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', '1 student imported successfully.');
        $this->assertTrue($class->hasStudent($kept->studentProfile));
        $this->assertFalse($class->hasStudent($extra->studentProfile));
        $this->assertTrue($class->hasStudent($already->studentProfile));
        $this->assertFalse($class->hasStudent($inactive->studentProfile));
        $this->assertSame($existingDetails, $class->classDetails()->where('student_id', $already->studentProfile->getKey())->get()->toJson());
        $this->assertSame($before, $this->userState());
        $event = AuditLog::where('action', 'ENROLL')->sole();
        $this->assertSame($teacher->id, $event->user_id);
        $detail = ClassDetail::findOrFail($event->auditable_id);
        $this->assertSame($kept->studentProfile->getKey(), $detail->student_id);
        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $kept->id]);
    }

    public function test_class_import_cannot_confirm_when_all_ready_students_are_removed(): void
    {
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $class = $this->classFor($teacher);
        $this->signIn($teacher);
        [$token] = $this->previewClassRoster($class, [$student->studentProfile->student_number]);
        $before = $this->userState();
        $this->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token, 'excluded_rows' => ['0']])
            ->assertSessionHasErrors('student_file')->assertSessionHas("class_student_imports.{$token}");
        $this->assertFalse($class->hasStudent($student->studentProfile));
        $this->assertSame($before, $this->userState());
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public static function invalidRosterExclusions(): array
    {
        return ['outside preview' => [[999]], 'negative row' => [[-1]], 'non-numeric row' => [['invalid']], 'duplicates' => [[0, 0]], 'not an array' => ['0']];
    }

    #[DataProvider('invalidRosterExclusions')]
    public function test_class_import_rejects_invalid_exclusion_rows_without_enrollment(mixed $excluded): void
    {
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $class = $this->classFor($teacher);
        $this->signIn($teacher);
        [$token] = $this->previewClassRoster($class, [$student->studentProfile->student_number]);
        $this->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token, 'excluded_rows' => $excluded])
            ->assertSessionHasErrors()->assertSessionHas("class_student_imports.{$token}");
        $this->assertFalse($class->hasStudent($student->studentProfile));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_class_import_does_not_silently_ignore_removals_from_an_older_preview(): void
    {
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $class = $this->classFor($teacher);
        $this->signIn($teacher);
        [$token, $preview] = $this->previewClassRoster($class, [$student->studentProfile->student_number]);
        unset($preview['rows'][0]['student_profile_id'], $preview['rows'][0]['status_key']);
        $this->withSession(["class_student_imports.{$token}" => $preview])->post(route('instructor.classes.students.import.confirm', $class), [
            'import_token' => $token, 'excluded_rows' => ['0'],
        ])->assertSessionHasErrors('student_file');
        $this->assertFalse($class->hasStudent($student->studentProfile));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_class_import_cannot_add_a_student_outside_its_validated_preview(): void
    {
        $teacher = $this->account('instructor');
        $allowed = $this->account('student');
        $outsider = $this->account('student');
        $class = $this->classFor($teacher);
        $this->signIn($teacher);
        [$token] = $this->previewClassRoster($class, [$allowed->studentProfile->student_number]);
        $this->post(route('instructor.classes.students.import.confirm', $class), [
            'import_token' => $token, 'student_profile_ids' => [$outsider->studentProfile->getKey()],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($class->hasStudent($allowed->studentProfile));
        $this->assertFalse($class->hasStudent($outsider->studentProfile));
    }

    public static function changedRosterAccounts(): array
    {
        return ['inactive account' => ['inactive'], 'student role removed' => ['role']];
    }

    #[DataProvider('changedRosterAccounts')]
    public function test_class_import_rechecks_remaining_students_and_can_exclude_a_changed_account(string $change): void
    {
        $teacher = $this->account('instructor');
        $kept = $this->account('student');
        $changed = $this->account('student');
        $class = $this->classFor($teacher);
        $this->signIn($teacher);
        [$token] = $this->previewClassRoster($class, [$kept->studentProfile->student_number, $changed->studentProfile->student_number]);
        if ($change === 'inactive') $changed->update(['status' => 'inactive']);
        else $changed->roles()->detach();
        $before = $this->userState();
        $this->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token])
            ->assertSessionHasErrors('student_file')->assertSessionHas("class_student_imports.{$token}");
        $this->assertFalse($class->hasStudent($kept->studentProfile));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->post(route('instructor.classes.students.import.confirm', $class), ['import_token' => $token, 'excluded_rows' => ['1']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($class->hasStudent($kept->studentProfile));
        $this->assertFalse($class->hasStudent($changed->studentProfile));
        $this->assertSame($before, $this->userState());
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function previewClassRoster(AcademicClass $class, array $numbers): array
    {
        $response = $this->post(route('instructor.classes.students.import.preview', $class), [
            'student_file' => $this->classRosterFile('csv', $numbers),
        ])->assertSessionHasNoErrors()->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return [$query['import_token'], session('class_student_imports.'.$query['import_token'])];
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

    public static function partialAccountEvents(): array
    {
        return ['one account' => ['account'], 'import summary' => ['summary'], 'different action' => ['update']];
    }

    #[DataProvider('partialAccountEvents')]
    public function test_each_new_account_gets_its_own_creation_event(string $mode): void
    {
        $actor = $this->account('super_admin');
        $historical = AuditLog::create([
            'user_id' => $actor->id, 'user_role' => 'super_admin', 'module' => 'Auth', 'action' => 'LOGIN',
            'description' => 'Existing history', 'created_at' => '2026-09-01 08:00:00',
            'metadata' => ['legacy' => 'preserve this record'],
        ]);
        $before = $historical->fresh()->getRawOriginal();
        $accountIds = [];
        Route::middleware('web')->post('/audit-tests/account-batch', function () use ($mode, &$accountIds) {
            $first = $this->account('student');
            $second = $this->account('student');
            $accountIds = [$first->id, $second->id];
            if ($mode === 'summary') {
                AuditLogger::logOrFail('IMPORT', 'Users', 'Imported two accounts');
            } else {
                AuditLogger::logOrFail($mode === 'account' ? 'CREATE' : 'UPDATE', 'Users', 'Account operation', $first);
            }

            return response()->json(['ok' => true]);
        });

        $this->signIn($actor)->postJson('/audit-tests/account-batch')->assertOk();
        $events = AuditLog::where('module', 'Users')->where('action', 'CREATE')->get();
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing($accountIds, $events->pluck('auditable_id')->all());
        foreach ($events as $event) {
            $this->assertSame($actor->id, $event->user_id);
            $this->assertSame('super_admin', $event->user_role);
            $this->assertSame(User::class, $event->auditable_type);
            $this->assertSame([$event->auditable_id], collect($event->metadata['changes'])
                ->where('model', User::class)->pluck('id')->all());
        }
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
    }

    public function test_missing_account_event_failure_rolls_back_the_entire_batch(): void
    {
        $actor = $this->account('super_admin');
        $before = $this->userState();
        $secondAccountId = $actor->id + 2;
        DB::statement("CREATE TRIGGER reject_second_account_event BEFORE INSERT ON audit_logs
            WHEN NEW.module = 'Users' AND NEW.action = 'CREATE' AND NEW.auditable_id = {$secondAccountId}
            BEGIN SELECT RAISE(ABORT, 'Second account audit failed'); END");
        Route::middleware('web')->post('/audit-tests/account-batch-failure', function () {
            $first = $this->account('student');
            $this->account('student');
            AuditLogger::logOrFail('CREATE', 'Users', 'First account created', $first);

            return response()->json(['ok' => true]);
        });
        $this->withoutExceptionHandling();
        try {
            $this->signIn($actor)->postJson('/audit-tests/account-batch-failure');
            $this->fail('Every account in the batch must have a durable creation event.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Second account audit failed', $exception->getMessage());
        }
        $this->assertSame($before, $this->userState());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_outside_the_recorder_rolls_back_its_transaction(): void
    {
        $this->actingAs($this->account('department_chair'));
        DB::statement("CREATE TRIGGER reject_direct_audit BEFORE INSERT ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Direct audit failed'); END");
        try {
            DB::transaction(function (): void {
                $program = Program::findOrFail(1);
                $program->update(['program_name' => 'Must roll back']);
                AuditLogger::log('UPDATE', 'Programs', 'Updated program', $program);
            });
            $this->fail('An audit failure must propagate outside HTTP recording too.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Direct audit failed', $exception->getMessage());
        }
        $this->assertSame('Program', Program::findOrFail(1)->program_name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_actor_identity_and_role_are_snapshotted_before_the_action(): void
    {
        $actor = $this->account('department_chair');
        $identity = ['id' => $actor->id, 'name' => $actor->displayName(), 'email' => $actor->email];
        Route::middleware('web')->post('/audit-tests/actor-rename', function () {
            $user = Auth::user();
            $user->update(['first_name' => 'Renamed', 'email' => 'after@example.test']);
            $user->roles->firstWhere('role_name', 'department_chair')->role_name = 'student';
            $user->roles()->sync([Role::firstOrCreate(['role_name' => 'student'])->getKey()]);
            $user->unsetRelation('roles');
            AuditLogger::logOrFail('UPDATE', 'Users', 'Changed account details', $user,
                ['actor' => ['id' => 99999, 'name' => 'Untrusted actor'], 'event_source' => 'System Migration']);

            return response()->json(['ok' => true]);
        });
        $this->signIn($actor)->postJson('/audit-tests/actor-rename')->assertOk();
        $event = AuditLog::where('module', 'Users')->sole();
        $this->assertSame($identity, $event->metadata['actor']);
        $this->assertSame('department_chair', $event->user_role);
        $this->assertSame('application', $event->metadata['event_source']);
        $this->assertSame('after@example.test', $actor->fresh()->email);
    }

    public function test_adding_a_profile_to_an_existing_account_is_an_update(): void
    {
        $actor = $this->account('super_admin');
        $target = $this->account('student');
        $target->studentProfile->delete();
        Route::middleware('web')->post('/audit-tests/profile-create', function () use ($target) {
            StudentProfile::create(['user_id' => $target->id, 'program_id' => 1, 'student_number' => 'RESTORED-123']);

            return response()->json(['ok' => true]);
        });
        $this->signIn($actor)->postJson('/audit-tests/profile-create')->assertOk();
        $event = AuditLog::where('module', 'Users')->sole();
        $this->assertSame('UPDATE', $event->action);
        $this->assertSame(User::class, $event->auditable_type);
        $this->assertSame($target->id, $event->auditable_id);
        $this->assertDatabaseCount('users', 2);
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

    public function test_all_model_bound_record_routes_use_public_ids(): void
    {
        $checked = [];
        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->signatureParameters(['subClass' => \Illuminate\Database\Eloquent\Model::class]) as $parameter) {
                $model = $parameter->getType()->getName();
                $this->assertContains('auth', $route->gatherMiddleware(), $route->getName().' must require authentication.');
                $this->assertSame('public_id', (new $model)->getRouteKeyName(), $route->getName().' exposes a primary key.');
                $checked[$model] = true;
            }
        }
        foreach ([Subject::class, ClassDetail::class, AssessmentItem::class, Submission::class] as $model) {
            $this->assertArrayHasKey($model, $checked);
        }
    }

    public function test_uuid_grading_links_render_and_owners_can_grade_without_changing_record_identifiers(): void
    {
        [$student, $published, $submission] = $this->securityAttempt(false, false, false);
        $teacher = $published->assessment->instructorProfile->user;
        $submission->update(['status' => Submission::STATUS_SUBMITTED, 'submitted_at' => now()]);
        $item = AssessmentItem::create(['assessment_id' => $published->assessment_id, 'question_text' => 'Explain the answer', 'item_type' => 'essay', 'points' => 5, 'sort_order' => 1]);
        $answer = SubmissionAnswer::create(['submission_id' => $submission->getKey(), 'assessment_item_id' => $item->getKey(), 'answer_text' => 'Private student answer']);
        $anotherAttempt = Submission::create(['publish_assessment_id' => $published->getKey(), 'student_profile_id' => $student->studentProfile->getKey(), 'attempt_number' => 2, 'status' => Submission::STATUS_IN_PROGRESS]);
        $anotherAnswer = SubmissionAnswer::create(['submission_id' => $anotherAttempt->getKey(), 'assessment_item_id' => $item->getKey(), 'answer_text' => 'Different attempt']);
        $anotherBefore = $anotherAnswer->fresh()->getRawOriginal();
        $key = $submission->public_id;
        $gradeUrl = route('instructor.assessments.submissions.grade', $submission);

        $this->signIn($teacher)->get($gradeUrl)->assertOk()->assertSee('Private student answer')
            ->assertSee('action="'.route('instructor.assessments.submissions.grade.update', $submission).'"', false);
        $this->get(route('instructor.assessments.results', $published))->assertOk()
            ->assertSee('href="'.$gradeUrl.'"', false)
            ->assertDontSee('/submissions/'.$submission->getKey().'/grade', false);
        $this->put(route('instructor.assessments.submissions.grade.update', $submission), [
            'essay_scores' => [$answer->getKey() => 3, $anotherAnswer->getKey() => 5],
            'essay_feedback' => [$answer->getKey() => 'Reviewed', $anotherAnswer->getKey() => 'Must not save'],
        ])->assertRedirect($gradeUrl)->assertSessionHasNoErrors();
        $this->assertSame('3.00', $answer->fresh()->earned_points);
        $this->assertSame($teacher->id, $answer->fresh()->checked_by);
        $this->assertSame($key, $submission->fresh()->public_id);
        $this->assertSame($submission->getKey(), $answer->fresh()->submission_id);
        $this->assertSame($student->studentProfile->getKey(), $submission->fresh()->student_profile_id);
        $this->assertSame($anotherBefore, $anotherAnswer->fresh()->getRawOriginal());
    }

    public function test_other_instructors_and_students_cannot_read_or_grade_a_guessed_submission_uuid(): void
    {
        [$student, $published, $submission] = $this->securityAttempt(false, false, false);
        $otherTeacher = $this->account('instructor');
        $submission->update(['status' => Submission::STATUS_SUBMITTED]);
        $item = AssessmentItem::create(['assessment_id' => $published->assessment_id, 'question_text' => 'Private item', 'item_type' => 'essay', 'points' => 5]);
        $answer = SubmissionAnswer::create(['submission_id' => $submission->getKey(), 'assessment_item_id' => $item->getKey(), 'answer_text' => 'Private answer']);
        $before = $answer->fresh()->getRawOriginal();
        $this->signIn($otherTeacher)->getJson(route('instructor.assessments.submissions.grade', $submission))
            ->assertForbidden()->assertDontSee('Private answer');
        $this->putJson(route('instructor.assessments.submissions.grade.update', $submission), ['essay_scores' => [$answer->getKey() => 5]])->assertForbidden();
        $this->signIn($student)->getJson(route('instructor.assessments.submissions.grade', $submission))->assertForbidden();
        $this->assertSame($before, $answer->fresh()->getRawOriginal());
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_old_numeric_grading_links_redirect_only_for_the_owner_and_cannot_accept_updates(): void
    {
        [$student, $published, $submission] = $this->securityAttempt(false, false, false);
        $teacher = $published->assessment->instructorProfile->user;
        $legacyUrl = '/instructor/submissions/'.$submission->getKey().'/grade';
        $before = $submission->fresh()->getRawOriginal();
        $this->get($legacyUrl)->assertRedirect(route('login'));
        $response = $this->signIn($teacher)->get($legacyUrl)->assertRedirect(route('instructor.assessments.submissions.grade', $submission));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->putJson($legacyUrl, ['essay_scores' => [1 => 999]])->assertStatus(405);
        $this->get('/instructor/submissions/999999/grade')->assertNotFound();
        $this->get('/instructor/submissions/not-a-uuid/grade')->assertNotFound();
        $this->get('/instructor/submissions/'.Str::uuid().'/grade')->assertNotFound();
        $this->signIn($this->account('instructor'))->get($legacyUrl)->assertNotFound();
        $this->signIn($student)->getJson($legacyUrl)->assertForbidden();
        $this->assertSame($before, $submission->fresh()->getRawOriginal());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_question_uuid_routes_require_the_correct_owner_and_parent_assessment(): void
    {
        [, $published] = $this->securityAttempt(false, false, false);
        $teacher = $published->assessment->instructorProfile->user;
        $assessment = Assessment::create(['instructor_id' => $teacher->instructorProfile->getKey(), 'subject_id' => $published->assessment->subject_id, 'title' => 'Editable draft', 'status' => Assessment::STATUS_DRAFT]);
        $item = AssessmentItem::create(['assessment_id' => $assessment->getKey(), 'question_text' => 'Original question', 'item_type' => 'essay', 'points' => 5]);
        $otherParentItem = AssessmentItem::create(['assessment_id' => $published->assessment_id, 'question_text' => 'Another assessment question', 'item_type' => 'essay', 'points' => 5]);
        $payload = ['question_text' => 'Updated question', 'item_type' => 'essay', 'points' => 5, 'is_required' => true];

        $this->signIn($teacher)->putJson(route('instructor.assessments.items.update', [$assessment, $otherParentItem]), $payload)->assertUnprocessable();
        $this->deleteJson(route('instructor.assessments.items.destroy', [$assessment, $otherParentItem]))->assertUnprocessable();
        $this->putJson('/instructor/assessments/'.$assessment->public_id.'/items/'.$item->getKey(), $payload)->assertNotFound();
        $this->deleteJson('/instructor/assessments/'.$assessment->public_id.'/items/'.$item->getKey())->assertNotFound();
        $this->signIn($this->account('instructor'))->putJson(route('instructor.assessments.items.update', [$assessment, $item]), $payload)->assertForbidden();
        $this->assertSame('Original question', $item->fresh()->question_text);
        $this->assertSame('Another assessment question', $otherParentItem->fresh()->question_text);
        $this->signIn($teacher)->put(route('instructor.assessments.items.update', [$assessment, $item]), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Updated question', $item->fresh()->question_text);
    }

    public function test_subject_uuid_routes_preserve_department_scope_and_reject_numeric_mutations(): void
    {
        $chair = $this->account('department_chair');
        $own = Subject::create(['department_id' => 1, 'subject_code' => 'OWN-UUID', 'subject_name' => 'Own subject']);
        $foreign = Subject::create(['department_id' => 2, 'subject_code' => 'OTHER-UUID', 'subject_name' => 'Other subject']);
        $payload = ['subject_code' => 'UPDATED-UUID', 'subject_name' => 'Changed subject', 'is_active' => true];
        $this->signIn($chair)->putJson(route('department-chair.subjects.update', $foreign), $payload)->assertUnprocessable();
        $this->deleteJson(route('department-chair.subjects.destroy', $foreign))->assertUnprocessable();
        $this->putJson('/department-chair/subjects/'.$own->getKey(), $payload)->assertNotFound();
        $this->deleteJson('/department-chair/subjects/'.$own->getKey())->assertNotFound();
        $this->assertSame('Other subject', $foreign->fresh()->subject_name);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->putJson(route('department-chair.subjects.update', $own), $payload)->assertOk();
        $this->assertSame('Changed subject', $own->fresh()->subject_name);
        $this->assertTrue(Str::isUuid($own->fresh()->public_id));
    }

    public function test_join_request_uuid_cannot_be_moved_between_classes_or_approved_by_another_teacher(): void
    {
        [, $published] = $this->securityAttempt(false, false, false);
        $teacher = $published->assessment->instructorProfile->user;
        $class = $published->class;
        $waitingStudent = $this->account('student');
        $request = ClassDetail::create(['class_id' => $class->getKey(), 'instructor_id' => $teacher->instructorProfile->getKey(), 'student_id' => $waitingStudent->studentProfile->getKey(), 'subject_id' => $published->assessment->subject_id, 'status' => ClassDetail::STATUS_PENDING, 'entry_method' => ClassDetail::METHOD_JOIN_CODE]);
        $otherTeacher = $this->account('instructor');
        $otherClass = $this->classFor($otherTeacher);
        $foreign = ClassDetail::create(['class_id' => $otherClass->getKey(), 'instructor_id' => $otherTeacher->instructorProfile->getKey(), 'student_id' => $waitingStudent->studentProfile->getKey(), 'subject_id' => $otherClass->subject_id, 'status' => ClassDetail::STATUS_PENDING, 'entry_method' => ClassDetail::METHOD_JOIN_CODE]);
        $this->signIn($teacher)->postJson(route('instructor.classes.join-requests.approve', [$class, $foreign]))->assertUnprocessable();
        $this->postJson(route('instructor.classes.join-requests.reject', [$class, $foreign]))->assertUnprocessable();
        $this->postJson('/instructor/classes/'.$class->public_id.'/join-requests/'.$request->getKey().'/approve')->assertNotFound();
        $this->signIn($otherTeacher)->postJson(route('instructor.classes.join-requests.approve', [$class, $request]))->assertForbidden();
        $this->assertSame(ClassDetail::STATUS_PENDING, $foreign->fresh()->status);
        $this->assertSame(ClassDetail::STATUS_PENDING, $request->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->signIn($teacher)->postJson(route('instructor.classes.join-requests.approve', [$class, $request]))->assertOk();
        $this->assertSame(ClassDetail::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(ClassDetail::STATUS_PENDING, $foreign->fresh()->status);
    }

    public static function securitySettings(): array
    {
        return [
            'all off' => [false, false, false],
            'copy only' => [true, false, false],
            'tab only' => [false, true, false],
            'screenshot only' => [false, false, true],
            'copy and tab' => [true, true, false],
            'copy and screenshot' => [true, false, true],
            'tab and screenshot' => [false, true, true],
            'all on' => [true, true, true],
        ];
    }

    #[DataProvider('securitySettings')]
    public function test_security_settings_independently_accept_only_enabled_events(bool $copy, bool $tab, bool $screenshot): void
    {
        [$student, $published, $submission] = $this->securityAttempt($copy, $tab, $screenshot);
        $this->signIn($student);
        $events = [
            'copy_attempt' => $copy, 'cut_attempt' => $copy, 'paste_attempt' => $copy, 'context_menu_attempt' => $copy,
            'tab_hidden' => $tab, 'window_blur' => $tab, 'floating_window' => false,
            'print_shortcut' => $screenshot, 'screenshot_shortcut' => $screenshot,
        ];
        foreach ($events as $type => $enabled) {
            $response = $this->postJson(route('student.assessments.security-events.store', $published), [
                'event_uuid' => (string) Str::uuid(), 'event_type' => $type,
            ]);
            if ($enabled) {
                $response->assertOk()->assertJsonPath('recorded', true)->assertJsonPath('should_auto_submit', false);
                $this->assertDatabaseHas('submission_security_events', ['submission_id' => $submission->getKey(), 'event_type' => $type]);
            } else {
                $response->assertUnprocessable()->assertJsonValidationErrors('event_type');
                $this->assertDatabaseMissing('submission_security_events', ['event_type' => $type]);
            }
        }
        $this->assertSame(count(array_filter($events)), SubmissionSecurityEvent::count());
        $this->assertSame(0, $submission->fresh()->warning_count);
    }

    public function test_screenshot_only_warning_limit_returns_auto_submit_and_logs_the_actual_submission(): void
    {
        [$student, $published, $submission] = $this->securityAttempt(false, false, true, 1);
        $this->signIn($student);
        $payload = ['event_uuid' => (string) Str::uuid(), 'event_type' => 'screenshot_shortcut'];

        $this->postJson(route('student.assessments.security-events.store', $published), $payload)
            ->assertOk()->assertJsonPath('recorded', true)->assertJsonPath('warning_count', 1)
            ->assertJsonPath('should_auto_submit', true);

        $event = AuditLog::where('action', 'SUBMIT')->sole();
        $this->assertSame($student->id, $event->user_id);
        $this->assertSame(Submission::class, $event->auditable_type);
        $this->assertSame($submission->getKey(), $event->auditable_id);
        $this->assertDatabaseHas('app_notifications', ['title' => 'Security Limit Reached']);

        $this->postJson(route('student.assessments.security-events.store', $published), $payload)
            ->assertOk()->assertJsonPath('recorded', false)->assertJsonPath('warning_count', 1);
        $this->assertSame(1, AuditLog::where('action', 'SUBMIT')->count());
        $this->assertSame(1, SubmissionSecurityEvent::count());
    }

    private function securityAttempt(bool $copy, bool $tab, bool $screenshot, int $limit = 0): array
    {
        foreach ([
            'assessment_items' => 'assessment_item_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, question_text TEXT, item_type TEXT, points REAL, is_required INTEGER, sort_order INTEGER, order_sensitive INTEGER, created_at DATETIME, updated_at DATETIME',
            'assessment_item_choices' => 'assessment_item_choice_id INTEGER PRIMARY KEY, assessment_item_id INTEGER, choice_text TEXT, is_correct INTEGER, sort_order INTEGER, created_at DATETIME, updated_at DATETIME',
            'publish_assessment' => 'publish_assessment_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, class_details_id INTEGER, publish_status TEXT, prevent_copy_paste INTEGER, detect_tab_switch INTEGER, screenshot_protection INTEGER, warning_limit INTEGER, created_at DATETIME, updated_at DATETIME',
            'submissions' => 'submission_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, publish_assessment_id INTEGER, student_profile_id INTEGER, attempt_number INTEGER, status TEXT, completion_reason TEXT, warning_count INTEGER, started_at DATETIME, last_activity_at DATETIME, submitted_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'submission_answers' => 'submission_answer_id INTEGER PRIMARY KEY, submission_id INTEGER, assessment_item_id INTEGER, assessment_item_choice_id INTEGER, answer_text TEXT, earned_points REAL, feedback TEXT, checked_by INTEGER, checked_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'passing_rate_settings' => 'year_level INTEGER PRIMARY KEY, passing_rate REAL',
            'submission_security_events' => 'submission_security_event_id INTEGER PRIMARY KEY, submission_id INTEGER, event_uuid TEXT UNIQUE, event_type TEXT, occurred_at DATETIME, request_fingerprint TEXT, user_agent TEXT, metadata TEXT, created_at DATETIME, updated_at DATETIME',
        ] as $table => $columns) {
            DB::statement("CREATE TABLE {$table} ({$columns})");
        }
        $teacher = $this->account('instructor');
        $student = $this->account('student');
        $subject = Subject::create(['department_id' => 1, 'subject_code' => 'SEC-101', 'subject_name' => 'Security settings']);
        $class = AcademicClass::create(['program_id' => 1, 'semester_id' => 1, 'year_level' => 1, 'section_name' => 'A', 'school_year' => '2026-2027']);
        $detail = ClassDetail::create([
            'class_id' => $class->getKey(), 'instructor_id' => $teacher->instructorProfile->getKey(),
            'subject_id' => $subject->getKey(), 'status' => ClassDetail::STATUS_APPROVED, 'entry_method' => ClassDetail::METHOD_CLASS_SETUP,
        ]);
        ClassDetail::create([
            'class_id' => $class->getKey(), 'instructor_id' => $teacher->instructorProfile->getKey(),
            'student_id' => $student->studentProfile->getKey(), 'subject_id' => $subject->getKey(),
            'status' => ClassDetail::STATUS_APPROVED, 'entry_method' => ClassDetail::METHOD_MANUAL_ADD,
        ]);
        $assessment = Assessment::create(['subject_id' => $subject->getKey(), 'instructor_id' => $teacher->instructorProfile->getKey(), 'title' => 'Security assessment']);
        $published = PublishAssessment::create([
            'assessment_id' => $assessment->getKey(), 'class_details_id' => $detail->getKey(),
            'publish_status' => PublishAssessment::STATUS_PUBLISHED, 'prevent_copy_paste' => $copy,
            'detect_tab_switch' => $tab, 'screenshot_protection' => $screenshot, 'warning_limit' => $limit,
        ]);
        $submission = Submission::create([
            'publish_assessment_id' => $published->getKey(), 'student_profile_id' => $student->studentProfile->getKey(),
            'attempt_number' => 1, 'status' => Submission::STATUS_IN_PROGRESS, 'warning_count' => 0,
        ]);

        return [$student, $published, $submission];
    }

    private function classRosterFile(string $format, array $numbers): UploadedFile
    {
        $contents = implode("\n", $format === 'txt' ? $numbers : ['student_number', ...$numbers])."\n";
        if ($format === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'class-roster-');
            try {
                $zip = new \ZipArchive;
                $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                $rows = '';
                foreach (['student_number', ...$numbers] as $index => $number) {
                    $row = $index + 1;
                    $value = htmlspecialchars($number, ENT_XML1);
                    $rows .= "<row r=\"{$row}\"><c r=\"A{$row}\" t=\"inlineStr\"><is><t>{$value}</t></is></c></row>";
                }
                $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
                $zip->close();
                $contents = file_get_contents($path);
            } finally {
                @unlink($path);
            }
        }

        return UploadedFile::fake()->createWithContent('roster.'.$format, $contents)
            ->mimeType($format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/plain');
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
