<?php

namespace Tests\Feature;

use App\Mail\InitialAccountPasswordMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserAccountService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class UserAccountCreationTest extends AccountFeatureTestCase
{
    #[DataProvider('portals')]
    public function test_account_creation_rolls_back_when_the_required_audit_write_fails(string $portal, string $actorRole): void
    {
        Mail::fake();
        $actor = $this->actor($actorRole);
        $payload = $this->creationPayload('student');
        DB::statement("CREATE TRIGGER reject_manual_audit BEFORE INSERT ON audit_logs
            WHEN NEW.module = 'Users' AND NEW.action = 'CREATE'
            BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->signInAccount($actor)->postJson(route("{$portal}.users.store"), $payload);
            $this->fail('A missing Create log must abort account creation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated audit failure', $exception->getMessage());
        }
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', $actorRole === 'super_admin' ? 1 : 2);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingSent();
    }

    public function test_audit_failure_outside_http_is_reported_and_propagated(): void
    {
        Log::spy();
        DB::statement("CREATE TRIGGER reject_optional_audit BEFORE INSERT ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Simulated optional audit failure'); END");
        try {
            AuditLogger::log('LOGIN', 'Auth', 'User logged in successfully.');
            $this->fail('Audit failure must not be swallowed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated optional audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        Log::shouldHaveReceived('error')->once()->with(
            'Audit log could not be recorded.',
            \Mockery::on(fn ($context) => $context['action'] === 'LOGIN'
                && $context['module'] === 'Auth' && $context['exception_type'] === QueryException::class)
        );
    }

    public function test_creation_requires_a_known_actor_and_preserves_existing_records_when_missing(): void
    {
        $this->actor('super_admin');
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $beforeRoles = DB::table('user_roles')->orderBy('user_id')->get()->toJson();
        try {
            app(UserAccountService::class)->createAccount($this->creationPayload('student'));
            $this->fail('Creation without a recorded creator must be rolled back.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('actor', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeRoles, DB::table('user_roles')->orderBy('user_id')->get()->toJson());
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_creation_requires_an_actor_role_snapshot(): void
    {
        $actor = $this->actor('super_admin');
        $actor->roles()->detach();
        $actor->unsetRelation('roles');
        $this->actingAs($actor);
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        try {
            app(UserAccountService::class)->createAccount($this->creationPayload('student'));
            $this->fail('Creation without a creator role must be rolled back.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('actor', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_creation_audit_requires_its_affected_account_reference(): void
    {
        $this->actingAs($this->actor('super_admin'));
        try {
            AuditLogger::logOrFail('CREATE', 'Users', 'Created account without a target');
            $this->fail('A creation event must identify the account that was created.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('account', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_request_user_agent_cannot_make_a_new_account_event_look_historical(): void
    {
        Mail::fake();
        $actor = $this->actor('super_admin');
        $payload = $this->creationPayload('student');
        $this->signInAccount($actor)->withHeader('User-Agent', 'System Migration')
            ->postJson(route('super-admin.users.store'), $payload)->assertCreated();
        $created = User::where('email', $payload['email'])->sole();
        $event = AuditLog::where('module', 'Users')->where('action', 'CREATE')->sole();
        $this->assertFalse($event->isSystemMigration());
        $this->assertSame($actor->displayName(), $event->actorName());
        $this->assertSame('application', $event->metadata['event_source']);
        $this->assertSame('System Migration', $event->user_agent);

        $placeholder = AuditLog::create([
            'user_id' => $created->id, 'user_role' => 'student', 'action' => 'CREATE', 'module' => 'Users',
            'description' => 'Historical placeholder', 'auditable_type' => User::class,
            'auditable_id' => $created->id, 'user_agent' => 'System Migration', 'created_at' => now(),
        ]);
        $original = $placeholder->fresh()->getRawOriginal();
        $visible = AuditLog::withoutSupersededUserCreations()->where('auditable_type', User::class)
            ->where('auditable_id', $created->id)->get();
        $this->assertSame([$event->getKey()], $visible->modelKeys());
        $this->assertSame('Creator unavailable', $placeholder->actorName());
        $this->assertSame($original, $placeholder->fresh()->getRawOriginal());
        $this->assertDatabaseCount('audit_logs', 2);
    }

    #[DataProvider('portals')]
    public function test_ajax_creation_rejects_duplicate_email_without_creating_an_account(string $portal, string $actorRole): void
    {
        Mail::fake();
        $actor = $this->actor($actorRole);
        $this->signInAccount($actor)->postJson(route("{$portal}.users.store"), [
            ...$this->creationPayload('student'), 'email' => $actor->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        Mail::assertNothingSent();
    }

    public function test_ajax_creation_preserves_dean_and_chair_academic_scopes(): void
    {
        Mail::fake();
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $actor = $this->actor($role);
            foreach (['instructor' => 'department_id', 'student' => 'program_id'] as $baseRole => $field) {
                if ($portal === 'department-chair' && $baseRole === 'instructor') {
                    continue;
                }
                $count = User::count();
                $this->signInAccount($actor)->postJson(route("{$portal}.users.store"), [
                    ...$this->creationPayload($baseRole), $field => 3,
                ])->assertUnprocessable()->assertJsonValidationErrors($field);
                $this->assertDatabaseCount('users', $count);
            }
        }
        Mail::assertNothingSent();
    }

    public function test_ajax_creation_designation_conflicts_return_validation_errors_without_saving(): void
    {
        Mail::fake();
        $dean = $this->actor('admin_dean');
        $this->actor('department_chair');
        $admin = $this->actor('super_admin');
        foreach (['super-admin' => [$admin, 'admin_dean'], 'admin-dean' => [$dean, 'department_chair']] as $portal => [$actor, $designation]) {
            $this->signInAccount($actor)->postJson(route("{$portal}.users.store"), [
                ...$this->creationPayload('instructor'), 'designation' => $designation,
            ])->assertUnprocessable()->assertJsonValidationErrors('authorizations');
        }
        $this->assertDatabaseCount('users', 3);
        Mail::assertNothingSent();
    }

    #[DataProvider('portals')]
    public function test_account_creation_does_not_email_after_a_late_audit_failure_and_can_be_retried(string $portal, string $actorRole): void
    {
        Mail::fake();
        $this->signInAccount($this->actor($actorRole));
        $payload = $this->creationPayload('student');
        DB::statement("CREATE TRIGGER reject_audit_metadata BEFORE UPDATE ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Late audit failure'); END");

        $this->postJson(route("{$portal}.users.store"), $payload)->assertStatus(500);

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingSent();

        DB::statement('DROP TRIGGER reject_audit_metadata');
        $this->postJson(route("{$portal}.users.store"), $payload)->assertCreated();
        $this->assertDatabaseHas('users', ['email' => $payload['email']]);
        $this->assertDatabaseCount('audit_logs', 1);
        Mail::assertSent(InitialAccountPasswordMail::class, 1);
    }

    #[DataProvider('portals')]
    public function test_failed_creation_does_not_flash_a_success_message(string $portal, string $actorRole): void
    {
        Mail::fake();
        $this->signInAccount($this->actor($actorRole));
        DB::statement("CREATE TRIGGER reject_audit_metadata BEFORE UPDATE ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Late audit failure'); END");

        $this->post(route("{$portal}.users.store"), $this->creationPayload('student'))
            ->assertStatus(500)->assertSessionMissing('status')->assertSessionMissing('mail_warning');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingSent();
    }

    #[DataProvider('portals')]
    public function test_ajax_creation_reports_email_failure_as_a_warning_after_saving(string $portal, string $actorRole): void
    {
        $this->partialMock(UserAccountService::class, function ($mock) {
            $mock->shouldReceive('sendAccountCreatedNotification')->once();
            $mock->shouldReceive('sendInitialPasswordEmail')->once()->andReturnUsing(function (User $user): bool {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertDatabaseHas('users', ['id' => $user->id]);
                $this->assertNotEmpty(AuditLog::sole()->metadata['changes']);

                return false;
            });
        });
        $this->signInAccount($this->actor($actorRole))->postJson(route("{$portal}.users.store"), $this->creationPayload('student'))
            ->assertCreated()->assertJson(['warning' => 'Account was created, but the initial password email was not delivered.']);
        $this->assertDatabaseHas('users', ['email' => 'new-account@example.test']);
    }

    public function test_normal_creation_preserves_its_email_warning_after_commit(): void
    {
        $this->partialMock(UserAccountService::class, function ($mock): void {
            $mock->shouldReceive('sendAccountCreatedNotification')->once();
            $mock->shouldReceive('sendInitialPasswordEmail')->once()->andReturnUsing(function (): bool {
                $this->assertSame(0, DB::transactionLevel());

                return false;
            });
        });
        $this->signInAccount($this->actor('super_admin'))->from(route('super-admin.users'))
            ->post(route('super-admin.users.store'), $this->creationPayload('student'))
            ->assertRedirect(route('super-admin.users'))
            ->assertSessionHas('status', 'User account created successfully.')
            ->assertSessionHas('mail_warning', 'Account was created, but the initial password email was not delivered.');
        $this->assertDatabaseHas('users', ['email' => 'new-account@example.test']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_normal_creation_still_redirects_with_a_success_message(): void
    {
        Mail::fake();
        $this->signInAccount($this->actor('super_admin'))->from(route('super-admin.users'))
            ->post(route('super-admin.users.store'), $this->creationPayload('student'))
            ->assertRedirect(route('super-admin.users'))->assertSessionHas('status', 'User account created successfully.');
    }

    #[DataProvider('portals')]
    public function test_live_availability_checks_duplicates_without_writing_accounts(string $portal, string $actorRole): void
    {
        $actor = $this->actor($actorRole);
        $teacher = $this->account(['instructor']);
        $student = $this->account(['student']);
        $this->signInAccount($actor);
        foreach ([
            'email' => $student->email,
            'employee_number' => $teacher->instructorProfile->employee_number,
            'student_number' => $student->studentProfile->student_number,
        ] as $field => $value) {
            $response = $this->postJson(route("{$portal}.users.availability"), compact('field', 'value'))
                ->assertOk()->assertJsonPath('field', $field)->assertJsonPath('available', false);
            $this->assertSame(['field', 'available', 'message'], array_keys($response->json()));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->postJson(route("{$portal}.users.availability"), [
                'field' => $field, 'value' => $field === 'email' ? 'unused@example.test' : 'UNUSED-999',
            ])->assertOk()->assertJsonPath('available', true);
        }
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }
}
