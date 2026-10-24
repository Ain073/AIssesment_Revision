<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AuditLog;
use App\Models\ClassDetail;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccountFeatureTestCase;

class EnrollmentAuditRecoveryTest extends AccountFeatureTestCase
{
    public function test_enrollment_backfill_recovers_only_approved_rosters_including_archived_classes(): void
    {
        $teacher = $this->actor('instructor');
        $classId = $this->enrollmentClass();
        $archivedClassId = $this->enrollmentClass(['section_name' => 'BSIT 2-B', 'archived_at' => '2026-09-20 12:00:00']);
        $expected = [];
        foreach ([ClassDetail::METHOD_JOIN_CODE, ClassDetail::METHOD_MANUAL_ADD, ClassDetail::METHOD_IMPORT_FILE] as $method) {
            $student = $this->account(['student']);
            $expected[] = $this->enrollmentDetail($student, $classId, ['entry_method' => $method]);
        }
        $expected[] = $this->enrollmentDetail($student, $archivedClassId);
        $this->enrollmentDetail($this->account(['student']), $classId, ['status' => ClassDetail::STATUS_PENDING]);
        $this->enrollmentDetail($this->account(['student']), $classId, ['status' => ClassDetail::STATUS_REJECTED]);
        DB::table('class_details')->insert([
            'class_id' => $classId, 'instructor_id' => $teacher->instructorProfile->getKey(),
            'student_id' => null, 'subject_id' => 1, 'status' => ClassDetail::STATUS_APPROVED,
            'entry_method' => ClassDetail::METHOD_CLASS_SETUP,
        ]);
        $rostersBefore = DB::table('class_details')->orderBy('class_details_id')->get()->toJson();
        $usersBefore = DB::table('users')->orderBy('id')->get()->toJson();

        $this->enrollmentMigration()->up();

        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertSame($rostersBefore, DB::table('class_details')->orderBy('class_details_id')->get()->toJson());
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($expected, AuditLog::orderBy('audit_log_id')->pluck('auditable_id')->all());
        foreach (AuditLog::all() as $event) {
            $this->assertSame('ENROLL', $event->action);
            $this->assertSame('Classes', $event->module);
            $this->assertSame(ClassDetail::class, $event->auditable_type);
            $this->assertNull($event->user_id);
            $this->assertNull($event->user_role);
            $this->assertNull($event->ip_address);
            $this->assertSame('Enrolling user unavailable', $event->actorName());
            $this->assertSame('Unknown', $event->userRoleLabel());
            $this->assertSame('', $event->actorSubtitle());
            $this->assertSame('2026-08-10 08:00:00', $event->getRawOriginal('created_at'));
            $this->assertSame('class_details.created_at', $event->metadata['timestamp_source']);
            $this->assertSame('2026-08-12 09:30:00', $event->metadata['roster_updated_at']);
            $this->assertStringContainsString('recovered from saved roster', $event->description);
        }
    }

    public function test_enrollment_backfill_matches_legacy_audits_by_student_and_class_and_preserves_them(): void
    {
        $admin = $this->actor('super_admin');
        $student = $this->account(['student']);
        $otherStudent = $this->account(['student']);
        $student->studentProfile->update(['student_number' => 'STU-(10).%_']);
        $otherStudent->studentProfile->update(['student_number' => 'STU-(10).%_0']);
        $classId = $this->enrollmentClass();
        $otherClassId = $this->enrollmentClass();
        $this->enrollmentDetail($student, $classId);
        $missingInSameClass = $this->enrollmentDetail($otherStudent, $classId);
        $missingInOtherClass = $this->enrollmentDetail($student, $otherClassId);
        $original = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'action' => 'ENROLL', 'module' => 'Classes',
            'description' => 'Enrolled student Previous Name (STU-(10).%_) into Previous Class Name',
            'auditable_type' => AcademicClass::class, 'auditable_id' => $classId,
            'created_at' => '2026-09-05 10:00:00',
        ]);
        $migration = $this->enrollmentMigration();
        $originalAttributes = $original->fresh()->getRawOriginal();
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertSame($originalAttributes, $original->fresh()->getRawOriginal());
        $this->assertSame([$missingInSameClass, $missingInOtherClass], AuditLog::where('auditable_type', ClassDetail::class)
            ->orderBy('audit_log_id')->pluck('auditable_id')->all());
    }

    public function test_enrollment_backfill_does_not_match_an_event_before_a_student_rejoined(): void
    {
        $student = $this->account(['student']);
        $classId = $this->enrollmentClass();
        $detailId = $this->enrollmentDetail($student, $classId);
        AuditLog::create([
            'action' => 'ENROLL', 'module' => 'Classes',
            'description' => "Enrolled student Previous Name ({$student->studentProfile->student_number}) into Class",
            'auditable_type' => AcademicClass::class, 'auditable_id' => $classId,
            'created_at' => '2026-08-01 08:00:00',
        ]);

        $this->enrollmentMigration()->up();

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ClassDetail::class, 'auditable_id' => $detailId]);
    }

    public function test_enrollment_backfill_keeps_stable_links_after_names_and_numbers_change(): void
    {
        $student = $this->account(['student']);
        $classId = $this->enrollmentClass();
        $detailId = $this->enrollmentDetail($student, $classId);
        $migration = $this->enrollmentMigration();
        $migration->up();
        $original = AuditLog::sole()->getRawOriginal();
        $student->studentProfile->update(['student_number' => 'CHANGED-NUMBER']);
        $student->update(['first_name' => 'Changed']);
        DB::table('classes')->where('class_id', $classId)->update(['section_name' => 'Renamed']);

        $migration->up();

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame($detailId, AuditLog::sole()->auditable_id);
        $this->assertSame($original, AuditLog::sole()->getRawOriginal());
    }

    public function test_enrollment_backfill_rolls_back_on_failure_and_can_be_retried(): void
    {
        $student = $this->account(['student']);
        $this->enrollmentDetail($student, $this->enrollmentClass());
        $second = $this->enrollmentDetail($student, $this->enrollmentClass());
        $migration = $this->enrollmentMigration();
        DB::statement("CREATE TRIGGER reject_enrollment_audit BEFORE INSERT ON audit_logs
            WHEN NEW.auditable_id = {$second}
            BEGIN SELECT RAISE(ABORT, 'Simulated enrollment recovery failure'); END");
        try {
            $migration->up();
            $this->fail('Enrollment recovery must not silently lose audit entries.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated enrollment recovery failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('class_details', 2);
        DB::statement('DROP TRIGGER reject_enrollment_audit');
        $migration->up();
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_enrollment_backfill_covers_multiple_chunks_and_displays_instructors_like_regular_logs(): void
    {
        $admin = $this->actor('super_admin');
        $teacher = $this->actor('instructor');
        $teacher->update(['first_name' => 'Enrollment', 'last_name' => 'Teacher']);
        $student = $this->account(['student']);
        foreach (range(1, 101) as $number) {
            $this->enrollmentDetail($student, $this->enrollmentClass());
        }
        $missingDate = $this->enrollmentDetail($student, $this->enrollmentClass(), ['created_at' => null, 'updated_at' => null]);
        $this->enrollmentMigration()->up();
        $this->enrollmentActorMigration()->up();
        $this->assertDatabaseCount('audit_logs', 102);
        $this->assertSame(102, AuditLog::where('user_id', $teacher->id)->where('user_role', 'instructor')->count());
        $this->assertNull(AuditLog::where('auditable_id', $missingDate)->sole()->metadata['timestamp_source']);

        $this->signInAccount($admin);
        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $this->get(route('super-admin.audit-logs', ['module' => 'Classes', 'action' => 'ENROLL']), $headers)
                ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 102)
                ->assertSee('Enrollment Teacher')->assertSee($teacher->email)
                ->assertDontSee('Enrolling user unavailable')->assertDontSee('Unknown')
                ->assertDontSee('Roster record date')->assertDontSee('Recovery date')
                ->assertDontSee('recovered from saved roster')->assertDontSee('Based on')
                ->assertSee($student->studentProfile->student_number)
                ->assertDontSee('System Migration');
            $this->get(route('super-admin.audit-logs', [
                'module' => 'Classes', 'action' => 'ENROLL', 'search' => $student->studentProfile->student_number,
                'from' => '2026-08-10', 'to' => '2026-08-10',
            ]), $headers)->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 101);
            $this->get(route('super-admin.audit-logs', [
                'module' => 'Classes', 'action' => 'ENROLL', 'role' => 'instructor', 'search' => 'Enrollment',
            ]), $headers)->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 102);
        }
    }

    public function test_enrollment_actor_recovery_links_profile_ids_to_users_and_preserves_original_history(): void
    {
        $admin = $this->actor('super_admin');
        $firstTeacher = $this->actor('instructor');
        $secondTeacher = $this->actor('instructor');
        $student = $this->account(['student']);
        $detailIds = [
            $this->enrollmentDetail($student, $this->enrollmentClass(), ['instructor_id' => $firstTeacher->instructorProfile->getKey()]),
            $this->enrollmentDetail($student, $this->enrollmentClass(), ['instructor_id' => $secondTeacher->instructorProfile->getKey()]),
        ];
        $this->enrollmentMigration()->up();
        $recovered = AuditLog::orderBy('audit_log_id')->get();
        $originals = collect([
            AuditLog::create([
                'user_id' => $admin->id, 'user_role' => 'super_admin', 'action' => 'ENROLL', 'module' => 'Classes',
                'description' => 'Original recorded event', 'auditable_type' => ClassDetail::class,
                'auditable_id' => $detailIds[0], 'created_at' => '2026-08-10 09:00:00',
            ]),
            AuditLog::create([
                'action' => 'ENROLL', 'module' => 'Assessments', 'description' => 'Unrelated migration event',
                'auditable_type' => ClassDetail::class, 'auditable_id' => $detailIds[0],
                'user_agent' => 'System Migration', 'metadata' => ['recovery_method' => 'approved_class_roster'],
                'created_at' => now(),
            ]),
            AuditLog::create([
                'action' => 'ENROLL', 'module' => 'Classes', 'description' => 'Enrollment with a missing source',
                'auditable_type' => ClassDetail::class, 'auditable_id' => 999999,
                'user_agent' => 'System Migration', 'metadata' => ['recovery_method' => 'approved_class_roster'],
                'created_at' => now(),
            ]),
        ])->mapWithKeys(fn ($event) => [$event->getKey() => $event->fresh()->getRawOriginal()]);
        $sourceBefore = DB::table('class_details')->orderBy('class_details_id')->get()->toJson();

        $migration = $this->enrollmentActorMigration();
        $migration->up();

        foreach ([$firstTeacher, $secondTeacher] as $index => $teacher) {
            $before = $recovered[$index];
            $after = $before->fresh();
            $this->assertSame($teacher->id, $after->user_id);
            $this->assertSame('instructor', $after->user_role);
            $this->assertSame($teacher->displayName(), $after->actorName());
            $this->assertSame($teacher->email, $after->actorSubtitle());
            $this->assertSame('Instructor', $after->userRoleLabel());
            $this->assertSame($before->description, $after->description);
            $this->assertSame($before->getRawOriginal('created_at'), $after->getRawOriginal('created_at'));
            $this->assertSame('class_details.instructor_id', $after->metadata['actor_source']);
            $this->assertSame($teacher->instructorProfile->getKey(), $after->metadata['instructor_profile_id']);
            $this->assertSame($before->metadata, array_intersect_key($after->metadata, $before->metadata));
            $this->assertStringNotContainsString('recovered from saved roster', $after->displayDescription());
        }
        $afterFirstRun = DB::table('audit_logs')->orderBy('audit_log_id')->get()->toJson();
        $migration->up();
        $migration->down();
        $this->assertSame($afterFirstRun, DB::table('audit_logs')->orderBy('audit_log_id')->get()->toJson());
        $this->assertSame($sourceBefore, DB::table('class_details')->orderBy('class_details_id')->get()->toJson());
        foreach ($originals as $id => $attributes) {
            $this->assertSame($attributes, AuditLog::findOrFail($id)->getRawOriginal());
        }
    }

    public function test_enrollment_actor_recovery_rolls_back_if_an_actor_update_fails(): void
    {
        $this->actor('instructor');
        $student = $this->account(['student']);
        $this->enrollmentDetail($student, $this->enrollmentClass());
        $this->enrollmentDetail($student, $this->enrollmentClass());
        $this->enrollmentMigration()->up();
        $before = DB::table('audit_logs')->orderBy('audit_log_id')->get()->toJson();
        $secondId = AuditLog::orderByDesc('audit_log_id')->first()->getKey();
        DB::statement("CREATE TRIGGER reject_enrollment_actor BEFORE UPDATE ON audit_logs
            WHEN NEW.audit_log_id = {$secondId}
            BEGIN SELECT RAISE(ABORT, 'Simulated actor recovery failure'); END");

        try {
            $this->enrollmentActorMigration()->up();
            $this->fail('Actor recovery must roll back a failed batch.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated actor recovery failure', $exception->getMessage());
        }

        $this->assertSame($before, DB::table('audit_logs')->orderBy('audit_log_id')->get()->toJson());
        DB::statement('DROP TRIGGER reject_enrollment_actor');
        $this->enrollmentActorMigration()->up();
        $this->assertSame(2, AuditLog::whereNotNull('user_id')->count());
    }

    private function enrollmentMigration(): Migration
    {
        if (! Schema::hasColumn('audit_logs', 'metadata')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->json('metadata')->nullable());
        }

        return require database_path('migrations/2026_10_20_000001_backfill_missing_class_enrollment_audit_logs.php');
    }

    private function enrollmentClass(array $attributes = []): int
    {
        return DB::table('classes')->insertGetId([
            'program_id' => 1, 'year_level' => 2, 'section_name' => 'BSIT 2-A', ...$attributes,
        ], 'class_id');
    }

    private function enrollmentActorMigration(): Migration
    {
        return require database_path('migrations/2026_10_20_000002_restore_instructor_actors_for_recovered_enrollments.php');
    }

    private function enrollmentDetail(User $student, int $classId, array $attributes = []): int
    {
        return DB::table('class_details')->insertGetId([
            'class_id' => $classId, 'instructor_id' => 1, 'subject_id' => 1,
            'student_id' => $student->studentProfile->getKey(), 'status' => ClassDetail::STATUS_APPROVED,
            'entry_method' => ClassDetail::METHOD_JOIN_CODE,
            'created_at' => '2026-08-10 08:00:00', 'updated_at' => '2026-08-12 09:30:00',
            ...$attributes,
        ], 'class_details_id');
    }
}
