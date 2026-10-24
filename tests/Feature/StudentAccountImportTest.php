<?php

namespace Tests\Feature;

use App\Mail\InitialAccountPasswordMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class StudentAccountImportTest extends AccountFeatureTestCase
{
    public function test_student_import_conflict_after_preview_does_not_create_accounts_or_audit_entries(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $token = $this->previewStudentImport('super-admin.users.students.import',
            "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
        );
        $conflict = $this->account(['student']);
        $conflict->update(['email' => 'maria.import@example.test']);
        $this->post(route('super-admin.users.students.import.confirm'), ['import_token' => $token])
            ->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseMissing('users', ['email' => 'juan.import@example.test']);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_student_import_rolls_back_create_logs_if_a_later_account_fails(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $token = $this->previewStudentImport('super-admin.users.students.import',
            "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
        );
        DB::statement("CREATE TRIGGER reject_imported_profile BEFORE INSERT ON student_profiles
            WHEN NEW.student_number = '2026-90002'
            BEGIN SELECT RAISE(ABORT, 'Simulated profile failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post(route('super-admin.users.students.import.confirm'), ['import_token' => $token]);
            $this->fail('The simulated profile failure should abort the import.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated profile failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_student_import_rolls_back_all_accounts_if_a_create_log_fails(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $token = $this->previewStudentImport('super-admin.users.students.import',
            "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
        );
        DB::statement("CREATE TRIGGER reject_imported_audit BEFORE INSERT ON audit_logs
            WHEN NEW.description LIKE '%maria.import@example.test%'
            BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post(route('super-admin.users.students.import.confirm'), ['import_token' => $token]);
            $this->fail('A missing Create log must abort the import.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingSent();
    }

    #[DataProvider('studentImportPortals')]
    public function test_import_draft_edits_are_validated_before_creating_the_corrected_account(string $actorRole, string $routePrefix): void
    {
        Mail::fake();
        $actor = $this->actor($actorRole);
        $this->signInAccount($actor);
        DB::table('programs')->insert(['program_id' => 4, 'department_id' => 1, 'program_name' => 'Another scoped program']);
        $before = DB::table('users')->get()->toJson();
        $token = $this->previewStudentImport($routePrefix,
            "student_number,first_name,middle_name,last_name,email\n2026-90001,Juan,,Wrong,invalid-email\n");
        $draft = session("student_account_imports.{$token}.rows");
        $draft[0] = [...$draft[0], 'student_number' => '23-SC-4217', 'first_name' => ' Ana ', 'middle_name' => ' M ', 'last_name' => "O'Neil", 'email' => 'ANA.CORRECTED@example.test', 'program_id' => 4, 'line' => 999, 'status' => 'inactive', 'base_role' => 'super_admin'];
        $review = $this->post(route("{$routePrefix}.review"), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing("student_account_imports.{$token}");
        parse_str(parse_url($review->headers->get('Location'), PHP_URL_QUERY), $query);
        $newToken = $query['import_token'];
        $this->assertNotSame($token, $newToken);
        $updated = session("student_account_imports.{$newToken}");
        $this->assertSame(1, $updated['summary']['ready']);
        $this->assertSame('Ana', $updated['rows'][0]['first_name']);
        $this->assertSame(4, $updated['rows'][0]['program_id']);
        $this->assertSame(2, $updated['rows'][0]['line']);
        $this->assertSame($before, DB::table('users')->get()->toJson());
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
        $this->get($review->headers->get('Location'))->assertOk()->assertSee('Save &amp; Validate', false)
            ->assertSee('data-import-field="first_name"', false)->assertSee('data-import-create', false);

        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $newToken, 'draft_rows' => json_encode($updated['rows'])])
            ->assertRedirect()->assertSessionHasNoErrors();
        $created = User::where('email', 'ana.corrected@example.test')->firstOrFail();
        $this->assertSame('Ana', $created->first_name);
        $this->assertSame('M', $created->middle_name);
        $this->assertSame("O'Neil", $created->last_name);
        $this->assertSame('23-SC-4217', $created->studentProfile->student_number);
        $this->assertSame(4, $created->studentProfile->program_id);
        $this->assertSame('active', $created->status);
        $this->assertTrue($created->hasRole('student'));
        $this->assertFalse($created->hasRole('super_admin'));
        $this->assertSame($actor->id, AuditLog::sole()->user_id);
        Mail::assertSent(InitialAccountPasswordMail::class, 1);
        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseCount('users', 2);
    }

    #[DataProvider('studentImportPortals')]
    public function test_import_rejects_edits_that_have_not_been_saved_and_validated(string $actorRole, string $routePrefix): void
    {
        Mail::fake();
        $this->signInAccount($this->actor($actorRole));
        $token = $this->previewStudentImport($routePrefix, "student_number,first_name,last_name,email\n2026-90001,Juan,Cruz,juan@example.test\n");
        $draft = session("student_account_imports.{$token}.rows");
        $draft[0]['email'] = 'changed@example.test';
        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertSessionHasErrorsIn('studentImport')->assertSessionHas("student_account_imports.{$token}");
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_import_edits_recheck_case_insensitive_duplicates_and_can_repair_invalid_records(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $existing = $this->account(['student']);
        $existing->studentProfile->update(['student_number' => '23-SC-4217']);
        $token = $this->previewStudentImport('super-admin.users.students.import',
            "student_number,first_name,last_name,email\n2026-90001,Juan,Cruz,juan@example.test\n2026-90002,Maria,Cruz,invalid-email\n");
        $draft = session("student_account_imports.{$token}.rows");
        $draft[0]['student_number'] = '23-sc-4217';
        $draft[0]['email'] = Str::upper($existing->email);
        $draft[1]['first_name'] = '';
        $review = $this->post(route('super-admin.users.students.import.review'), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($review->headers->get('Location'), PHP_URL_QUERY), $query);
        $preview = session('student_account_imports.'.$query['import_token']);
        $this->assertSame(['total' => 2, 'ready' => 0, 'duplicate' => 1, 'invalid' => 1], $preview['summary']);
        $this->assertStringContainsString('Student number is duplicated', $preview['rows'][0]['message']);
        $this->assertStringContainsString('First name is required', $preview['rows'][1]['message']);
        $draft = $preview['rows'];
        $draft[0]['student_number'] = '2026-90001';
        $draft[0]['email'] = 'juan.fixed@example.test';
        $draft[1]['first_name'] = 'Maria';
        $draft[1]['email'] = 'maria.fixed@example.test';
        $review = $this->post(route('super-admin.users.students.import.review'), ['import_token' => $query['import_token'], 'draft_rows' => json_encode($draft)])
            ->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($review->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(2, session('student_account_imports.'.$query['import_token'].'.summary.ready'));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_import_review_keeps_department_program_scope_and_creator_ownership(): void
    {
        Mail::fake();
        $actor = $this->actor('department_chair');
        $this->signInAccount($actor);
        $token = $this->previewStudentImport('department-chair.students.import', "student_number,first_name,last_name,email\n2026-90001,Juan,Cruz,juan@example.test\n");
        $draft = session("student_account_imports.{$token}.rows");
        $draft[0]['program_id'] = 3;
        $review = $this->post(route('department-chair.students.import.review'), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($review->headers->get('Location'), PHP_URL_QUERY), $query);
        $preview = session('student_account_imports.'.$query['import_token']);
        $this->assertSame(0, $preview['summary']['ready']);
        $this->assertStringContainsString('outside your assigned scope', $preview['rows'][0]['message']);
        $this->signInAccount($this->actor('department_chair'));
        $this->post(route('department-chair.students.import.review'), ['import_token' => $query['import_token'], 'draft_rows' => json_encode($draft)])
            ->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_import_review_supports_all_200_records_as_one_draft_payload(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $csv = "student_number,first_name,last_name,email\n";
        for ($index = 1; $index <= 200; $index++) {
            $csv .= "2026-{$index},Student{$index},Imported,student{$index}@example.test\n";
        }
        $token = $this->previewStudentImport('super-admin.users.students.import', $csv);
        $draft = session("student_account_imports.{$token}.rows");
        $draft[199]['last_name'] = 'Corrected';
        $review = $this->post(route('super-admin.users.students.import.review'), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($review->headers->get('Location'), PHP_URL_QUERY), $query);
        $preview = session('student_account_imports.'.$query['import_token']);
        $this->assertSame(200, $preview['summary']['ready']);
        $this->assertSame('Corrected', $preview['rows'][199]['last_name']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_import_review_rejects_malformed_rows_and_expired_drafts_without_writes(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'));
        $token = $this->previewStudentImport('super-admin.users.students.import', "student_number,first_name,last_name,email\n2026-90001,Juan,Cruz,juan@example.test\n");
        $draft = session("student_account_imports.{$token}.rows");
        $nested = $draft;
        $nested[0]['first_name'] = ['invalid'];
        foreach (['{', '{}', '[]', json_encode([$draft[0], $draft[0]]), json_encode($nested)] as $payload) {
            $this->post(route('super-admin.users.students.import.review'), ['import_token' => $token, 'draft_rows' => $payload])
                ->assertSessionHasErrorsIn('studentImport');
        }
        $this->post(route('super-admin.users.students.import.review'), ['import_token' => $token, 'draft_rows' => ['invalid']])
            ->assertSessionHasErrorsIn('studentImport');
        $this->get(route('super-admin.users', ['import_token' => $token]))->assertOk();
        $stored = session("student_account_imports.{$token}");
        $stored['expires_at'] = now()->subMinute()->timestamp;
        $this->withSession(["student_account_imports.{$token}" => $stored])
            ->post(route('super-admin.users.students.import.review'), ['import_token' => $token, 'draft_rows' => json_encode($draft)])
            ->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    #[DataProvider('accountUpdates')]
    public function test_ajax_creation_saves_scoped_accounts_profiles_and_notifications(string $portal, string $actorRole, string $baseRole): void
    {
        Mail::fake();
        $actor = $this->actor($actorRole);
        $payload = $this->creationPayload($baseRole);
        $this->signInAccount($actor)->postJson(route("{$portal}.users.store"), $payload)
            ->assertCreated()->assertExactJson(['message' => 'User account created successfully.', 'warning' => null]);

        $created = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue($created->hasRole($baseRole));
        $this->assertTrue($created->must_change_password);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $created->id, 'title' => 'Account Created']);
        if ($baseRole === 'instructor') {
            $this->assertSame(1, $created->instructorProfile->department_id);
        } else {
            $this->assertSame(1, $created->studentProfile->program_id);
        }
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id, 'user_role' => $actorRole,
            'action' => 'CREATE', 'module' => 'Users',
            'auditable_type' => User::class, 'auditable_id' => $created->id,
            'description' => "Created {$baseRole} account for {$created->displayName()} ({$created->email})",
        ]);
        Mail::assertSent(InitialAccountPasswordMail::class);
    }

    #[DataProvider('studentImportPortals')]
    public function test_import_does_not_email_after_a_late_audit_failure_and_keeps_its_preview_for_retry(string $actorRole, string $routePrefix): void
    {
        Mail::fake();
        $this->signInAccount($this->actor($actorRole));
        $token = $this->previewStudentImport($routePrefix,
            "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
        );
        DB::statement("CREATE TRIGGER reject_audit_metadata BEFORE UPDATE ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Late audit failure'); END");

        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])->assertStatus(500);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();

        DB::statement('DROP TRIGGER reject_audit_metadata');
        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2 student accounts created successfully.')
            ->assertSessionMissing('mail_warning');
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 2);
        Mail::assertSent(InitialAccountPasswordMail::class, 2);
    }

    #[DataProvider('studentImportPortals')]
    public function test_import_reports_email_failure_after_accounts_and_audits_have_committed(string $actorRole, string $routePrefix): void
    {
        Mail::fake();
        $this->signInAccount($this->actor($actorRole));
        $token = $this->previewStudentImport($routePrefix,
            "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
        );
        $this->partialMock(UserAccountService::class, function ($mock): void {
            $mock->shouldReceive('sendInitialPasswordEmail')->twice()->andReturnUsing(function (User $user): bool {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertDatabaseHas('users', ['id' => $user->id]);
                $this->assertDatabaseCount('audit_logs', 2);

                return false;
            });
        });

        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2 student accounts created successfully.')
            ->assertSessionHas('mail_warning', 'Some initial password emails were not delivered.');
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 2);
    }
}
