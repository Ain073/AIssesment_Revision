<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserCreationAuditRecovery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class UserCreationAuditRecoveryTest extends AccountFeatureTestCase
{
    public function test_missing_account_creation_backfill_is_idempotent_and_preserves_accounts_and_existing_logs(): void
    {
        $admin = $this->actor('super_admin');
        $missing = $this->account(['student']);
        $existing = $this->account(['instructor']);
        DB::table('users')->where('id', $missing->id)->update([
            'created_at' => '2026-09-25 20:24:44', 'updated_at' => '2026-10-01 12:30:00',
        ]);
        $original = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'CREATE',
            'description' => 'Original creation event', 'auditable_type' => User::class,
            'auditable_id' => $existing->id, 'created_at' => '2026-09-10 10:00:00',
        ]);
        AuditLog::create([
            'module' => 'Classes', 'action' => 'CREATE', 'description' => 'Unrelated event',
            'auditable_type' => User::class, 'auditable_id' => $missing->id, 'created_at' => now(),
        ]);
        $originalAttributes = $original->fresh()->getRawOriginal();
        $accountsBefore = DB::table('users')->orderBy('id')->get()->toJson();

        $migration = require database_path('migrations/2026_10_17_000002_backfill_missing_user_creation_audit_logs.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertSame($accountsBefore, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($originalAttributes, $original->fresh()->getRawOriginal());
        foreach ([$admin, $missing, $existing] as $account) {
            $this->assertSame(1, AuditLog::where('module', 'Users')->where('action', 'CREATE')
                ->where('auditable_type', User::class)->where('auditable_id', $account->id)->count());
        }
        $reconstructed = AuditLog::where('module', 'Users')->where('auditable_id', $missing->id)->firstOrFail();
        $this->assertNull($reconstructed->user_id);
        $this->assertNull($reconstructed->user_role);
        $this->assertNull($reconstructed->ip_address);
        $this->assertSame('2026-09-25 20:24:44', $reconstructed->getRawOriginal('created_at'));
        $this->assertStringContainsString('Reconstructed creation entry', $reconstructed->description);
        $this->assertSame('Creator unavailable', $reconstructed->actorName());
    }

    public function test_missing_creation_backfill_rolls_back_if_a_log_cannot_be_inserted(): void
    {
        $first = $this->account(['student']);
        $second = $this->account(['student']);
        DB::statement("CREATE TRIGGER reject_backfilled_audit BEFORE INSERT ON audit_logs
            WHEN NEW.auditable_id = {$second->id}
            BEGIN SELECT RAISE(ABORT, 'Simulated backfill failure'); END");
        $migration = require database_path('migrations/2026_10_17_000002_backfill_missing_user_creation_audit_logs.php');
        try {
            $migration->up();
            $this->fail('The backfill must not silently lose an audit entry.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated backfill failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_missing_creation_backfill_covers_accounts_after_the_first_chunk(): void
    {
        $rows = [];
        foreach (range(1, 101) as $number) {
            $rows[] = [
                'public_id' => (string) Str::uuid(), 'name' => "Account {$number}",
                'first_name' => 'Account', 'last_name' => (string) $number,
                'email' => "backfill{$number}@example.test", 'password' => 'unused-fixture-value',
                'status' => 'active', 'created_at' => '2026-09-25 20:24:44', 'updated_at' => '2026-10-01 12:30:00',
            ];
        }
        DB::table('users')->insert($rows);
        $accountsBefore = DB::table('users')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_17_000002_backfill_missing_user_creation_audit_logs.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('audit_logs', 101);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Users', 'action' => 'CREATE', 'auditable_type' => User::class,
            'auditable_id' => 101, 'user_id' => null, 'created_at' => '2026-09-25 20:24:44',
        ]);
        $this->assertSame($accountsBefore, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_import_history_recovers_creator_and_event_time_without_changing_accounts_or_original_audits(): void
    {
        [$admin, $students, $source] = $this->legacyImportFixture();
        DB::table('users')->where('id', $students[0]->id)->update(['created_at' => '2026-09-11 11:03:13']);
        DB::table('users')->where('id', $students[1]->id)->update(['created_at' => '2026-09-12 11:03:15']);
        // A later program transfer does not change the original import evidence.
        DB::table('student_profiles')->where('user_id', $students[1]->id)
            ->update(['program_id' => 2, 'updated_at' => '2026-10-01 12:00:00']);
        $placeholderMigration = require database_path('migrations/2026_10_17_000002_backfill_missing_user_creation_audit_logs.php');
        $placeholderMigration->up();
        $usersBefore = DB::table('users')->orderBy('id')->get()->toJson();
        $profilesBefore = DB::table('student_profiles')->orderBy('student_profile_id')->get()->toJson();
        $rolesBefore = DB::table('user_roles')->orderBy('user_id')->get()->toJson();
        $originalColumns = Schema::getColumnListing('audit_logs');
        $originalIds = AuditLog::pluck('audit_log_id')->all();
        $auditsBefore = DB::table('audit_logs')->select($originalColumns)->orderBy('audit_log_id')->get()->toJson();

        $migration = require database_path('migrations/2026_10_17_000003_recover_user_creation_audits_from_import_history.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseCount('audit_logs', 6);
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($profilesBefore, DB::table('student_profiles')->orderBy('student_profile_id')->get()->toJson());
        $this->assertSame($rolesBefore, DB::table('user_roles')->orderBy('user_id')->get()->toJson());
        $this->assertSame($auditsBefore, DB::table('audit_logs')->select($originalColumns)
            ->whereIn('audit_log_id', $originalIds)->orderBy('audit_log_id')->get()->toJson());
        foreach ($students as $student) {
            $event = AuditLog::where('auditable_id', $student->id)
                ->where('user_agent', UserCreationAuditRecovery::USER_AGENT)->sole();
            $this->assertSame($admin->id, $event->user_id);
            $this->assertSame('super_admin', $event->user_role);
            $this->assertSame($student->studentProfile->fresh()->getRawOriginal('created_at'), $event->getRawOriginal('created_at'));
            $this->assertSame($source->getKey(), $event->metadata['source_import_audit_log_id']);
            $this->assertSame(UserCreationAuditRecovery::METHOD, $event->metadata['recovery_method']);
            $this->assertSame("Created student account for {$student->displayName()} ({$student->email}) via CSV import", $event->description);
        }

        $this->signInAccount($admin)->get(route('super-admin.audit-logs', [
            'module' => 'Users', 'action' => 'CREATE', 'role' => 'super_admin',
            'from' => '2026-09-29', 'to' => '2026-09-29',
        ]))->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 2)
            ->assertSee($students[0]->email)->assertSee($students[1]->email)
            ->assertSee('Sep 29, 2026')->assertSee('11:03 AM')
            ->assertDontSee('Creator unavailable')->assertDontSee('Reconstructed')
            ->assertDontSee('Import History Recovery')->assertDontSee('source_import_audit_log_id');
        $this->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'CREATE']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 3)
            ->assertDontSee('Reconstructed');
        $this->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'IMPORT']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public static function ambiguousLegacyImports(): array
    {
        return [
            'wrong batch count' => ['count'], 'same-second imports' => ['overlap'],
            'missing role timestamp' => ['role'], 'mixed programs' => ['program'],
            'existing original creation' => ['original'], 'missing importer' => ['actor'],
            'outside matching window' => ['time'], 'unsupported earlier import boundary' => ['boundary'],
        ];
    }

    #[DataProvider('ambiguousLegacyImports')]
    public function test_ambiguous_import_history_does_not_guess_account_creators(string $case): void
    {
        [$admin, $students, $source] = $this->legacyImportFixture();
        if ($case === 'count') {
            $source->update(['description' => 'Imported 3 student account/s via CSV']);
        } elseif ($case === 'overlap' || $case === 'boundary') {
            AuditLog::create([
                'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'IMPORT',
                'description' => $case === 'overlap' ? 'Imported 2 student account/s via CSV' : 'Unsupported legacy summary',
                'created_at' => $case === 'overlap' ? $source->created_at : '2026-09-29 11:03:14',
            ]);
        } elseif ($case === 'role') {
            DB::table('user_roles')->where('user_id', $students[1]->id)->update(['created_at' => '2026-09-29 11:00:00']);
        } elseif ($case === 'program') {
            DB::table('student_profiles')->where('user_id', $students[1]->id)->update(['program_id' => 2]);
        } elseif ($case === 'original') {
            AuditLog::create([
                'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'CREATE',
                'description' => 'Original recorded creation', 'auditable_type' => User::class,
                'auditable_id' => $students[0]->id, 'created_at' => '2026-09-29 11:03:13',
            ]);
        } elseif ($case === 'actor') {
            $source->update(['user_id' => null]);
        } elseif ($case === 'time') {
            $this->stampImportedStudent($students[0], '2026-09-29 11:01:00');
        }
        $usersBefore = DB::table('users')->orderBy('id')->get()->toJson();
        $originalIds = AuditLog::pluck('audit_log_id')->all();
        $migration = require database_path('migrations/2026_10_17_000003_recover_user_creation_audits_from_import_history.php');
        $migration->up();
        $this->assertSame(0, AuditLog::where('user_agent', UserCreationAuditRecovery::USER_AGENT)->count());
        $this->assertSame($originalIds, AuditLog::pluck('audit_log_id')->all());
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_import_history_recovery_respects_previous_batch_and_rolls_back_on_audit_failure(): void
    {
        [$admin, $students, $source] = $this->legacyImportFixture();
        $later = [$this->account(['student']), $this->account(['student'])];
        $this->stampImportedStudent($later[0], '2026-09-29 11:03:50');
        $this->stampImportedStudent($later[1], '2026-09-29 11:03:52');
        $laterSource = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'IMPORT',
            'description' => 'Imported 2 student account/s via CSV', 'created_at' => '2026-09-29 11:04:00',
        ]);
        DB::statement("CREATE TRIGGER reject_recovered_audit BEFORE INSERT ON audit_logs
            WHEN NEW.auditable_id = {$later[1]->id} AND NEW.user_agent = 'Import History Recovery'
            BEGIN SELECT RAISE(ABORT, 'Simulated recovery failure'); END");
        $migration = require database_path('migrations/2026_10_17_000003_recover_user_creation_audits_from_import_history.php');
        try {
            $migration->up();
            $this->fail('The recovery must not leave partially recovered batches.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated recovery failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 2);
        DB::statement('DROP TRIGGER reject_recovered_audit');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('audit_logs', 6);
        foreach ([[$students, $source], [$later, $laterSource]] as [$batch, $import]) {
            foreach ($batch as $student) {
                $event = AuditLog::where('auditable_id', $student->id)->where('action', 'CREATE')->sole();
                $this->assertSame($import->getKey(), $event->metadata['source_import_audit_log_id']);
            }
        }
    }

    public function test_original_create_with_a_null_user_agent_hides_only_its_matching_placeholder(): void
    {
        [$admin, $students] = $this->legacyImportFixture();
        $migration = require database_path('migrations/2026_10_17_000002_backfill_missing_user_creation_audit_logs.php');
        $migration->up();
        $original = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'CREATE',
            'description' => 'Original recorded creation', 'auditable_type' => User::class,
            'auditable_id' => $students[0]->id, 'created_at' => now(),
        ]);
        $visible = AuditLog::withoutSupersededUserCreations()->where('module', 'Users')->where('action', 'CREATE')->get();
        $this->assertCount(3, $visible);
        $this->assertTrue($visible->contains(fn ($event) => $event->getKey() === $original->getKey()));
        $this->assertCount(1, $visible->where('auditable_id', $students[1]->id));
        $this->assertDatabaseCount('audit_logs', 5);

        // Deleting the creator nulls this FK; it must not resurrect a duplicate placeholder.
        $original->update(['user_id' => null]);
        $visible = AuditLog::withoutSupersededUserCreations()->where('module', 'Users')->where('action', 'CREATE')->get();
        $this->assertCount(3, $visible);
        $this->assertCount(1, $visible->where('auditable_id', $students[0]->id));
        $this->assertSame('Deleted Account', $original->fresh()->actorName());
    }

    private function legacyImportFixture(): array
    {
        $admin = $this->actor('super_admin');
        $admin->update(['first_name' => 'Admin', 'last_name' => 'Operator']);
        $students = [$this->account(['student']), $this->account(['student'])];
        $this->stampImportedStudent($students[0], '2026-09-29 11:03:13');
        $this->stampImportedStudent($students[1], '2026-09-29 11:03:15');
        $source = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'IMPORT',
            'description' => 'Imported 2 student account/s via CSV', 'created_at' => '2026-09-29 11:03:22',
        ]);

        return [$admin, $students, $source];
    }

    private function stampImportedStudent(User $student, string $timestamp): void
    {
        DB::table('student_profiles')->where('user_id', $student->id)->update(['created_at' => $timestamp, 'updated_at' => $timestamp]);
        DB::table('user_roles')->where('user_id', $student->id)->update(['created_at' => $timestamp]);
    }
}
