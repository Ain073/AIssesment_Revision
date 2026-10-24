<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountFeatureTestCase;

class AuditLogViewTest extends AccountFeatureTestCase
{
    public function test_audit_search_finds_matches_before_pagination_and_keeps_other_filters(): void
    {
        $admin = $this->actor('super_admin');
        foreach (range(1, 30) as $number) {
            AuditLog::create(['user_id' => $admin->id, 'action' => 'CREATE', 'module' => 'Programs', 'description' => "Noise {$number}", 'created_at' => now()]);
        }
        AuditLog::create(['user_id' => $admin->id, 'action' => 'CREATE', 'module' => 'Programs', 'description' => 'Needle record', 'created_at' => now()->subDay()]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'DELETE', 'module' => 'Programs', 'description' => 'Needle record', 'created_at' => now()]);

        $this->signInAccount($admin)->get(route('super-admin.audit-logs', ['search' => 'needle', 'action' => 'CREATE']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->description === 'Needle record')
            ->assertSee('data-record-search-input', false);
    }

    public function test_audit_actions_are_limited_to_existing_visible_logs_for_the_selected_feature(): void
    {
        $admin = $this->actor('super_admin');
        foreach ([
            ['Auth', 'LOGIN'], ['Auth', 'LOGOUT'], ['Auth', 'LOGIN'],
            ['Assessments', 'PUBLISH'], ['Assessments', 'SUBMIT'],
            ['Reports', 'FINALIZE'], ['Users', 'CREATE'], ['Users', 'UPDATE'],
        ] as [$module, $action]) {
            AuditLog::create([
                'user_id' => $admin->id, 'module' => $module, 'action' => $action,
                'description' => "{$module} {$action}", 'created_at' => now(),
            ]);
        }
        $this->signInAccount($admin);
        foreach ([
            'Auth' => ['LOGIN', 'LOGOUT'], 'Assessments' => ['PUBLISH', 'SUBMIT'],
            'Reports' => ['FINALIZE'], 'Users' => ['CREATE', 'UPDATE'], 'Subjects' => [],
            '' => ['CREATE', 'FINALIZE', 'LOGIN', 'LOGOUT', 'PUBLISH', 'SUBMIT', 'UPDATE'],
        ] as $module => $expected) {
            $this->get(route('super-admin.audit-logs', ['module' => $module]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertViewHas('actions', fn ($actions) => $actions->all() === $expected);
        }
        $this->get(route('super-admin.audit-logs', ['module' => 'Auth', 'search' => 'missing event']))
            ->assertOk()->assertViewHas('actions', fn ($actions) => $actions->all() === ['LOGIN', 'LOGOUT'])
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
    }

    public function test_past_and_new_user_updates_are_visible_and_filterable_without_rewriting_logs(): void
    {
        $admin = $this->actor('super_admin');
        $originalDescription = 'Updated student account for Previous Name (Status: active)';
        $old = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin',
            'module' => 'Users', 'action' => 'UPDATE',
            'description' => $originalDescription, 'created_at' => now()->subYear(),
        ]);
        $new = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin',
            'module' => 'Users', 'action' => 'UPDATE',
            'description' => 'Updated student account details (Status: active)', 'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->id, 'module' => 'Users', 'action' => 'CREATE',
            'description' => 'Created another account', 'created_at' => now(),
        ]);
        $this->signInAccount($admin);
        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $this->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'UPDATE']), $headers)
                ->assertOk()
                ->assertViewHas('actions', fn ($actions) => $actions->all() === ['CREATE', 'UPDATE'])
                ->assertViewHas('logs', fn ($logs) => $logs->total() === 2
                    && $logs->pluck('audit_log_id')->all() === [$new->getKey(), $old->getKey()])
                ->assertSee($originalDescription)
                ->assertSee('Updated student account details (Status: active)')
                ->assertDontSee('Current linked account:')
                ->assertDontSee('Created another account');
        }
        $this->assertSame($originalDescription, $old->fresh()->description);
        $this->assertSame(3, AuditLog::count());
    }

    public function test_generic_updates_display_only_the_verified_current_target_without_rewriting_history(): void
    {
        $admin = $this->actor('super_admin');
        $student = $this->account(['student']);
        $student->update(['first_name' => 'Linked', 'last_name' => '<Student>']);
        $teacher = $this->account(['instructor']);
        $teacher->update(['first_name' => 'Linked', 'last_name' => 'Teacher']);
        $nameOnly = $this->account(['student']);
        $nameOnly->update(['first_name' => 'Name', 'last_name' => 'Only']);
        $nameOnly->studentProfile()->delete();
        $logs = collect([$student, $teacher, $nameOnly])->map(fn (User $target) => AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Users', 'action' => 'UPDATE',
            'description' => 'Updated student account details (Status: active)',
            'auditable_type' => User::class, 'auditable_id' => $target->id, 'created_at' => now(),
        ]));
        $studentDescription = $logs->first()->description;
        $this->signInAccount($admin);
        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $response = $this->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'UPDATE']), $headers)
                ->assertOk()->assertSee('Current linked account: Linked <Student> (Student No.: '.$student->studentProfile->student_number.')')
                ->assertSee('Current linked account: Linked Teacher (Employee No.: '.$teacher->instructorProfile->employee_number.')')
                ->assertSee('Current linked account: Name Only')
                ->assertDontSee('Linked <Student>', false);
            $response->assertViewHas('logs', function ($page) use ($admin) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                foreach ($page as $log) {
                    $this->assertSame($admin->id, $log->user_id);
                    $this->assertNotNull($log->currentAccountLabel());
                }
                $this->assertCount(0, DB::getQueryLog(), 'Target display must use eager-loaded profiles.');
                DB::disableQueryLog();

                return true;
            });
        }
        $this->assertSame($studentDescription, $logs->first()->fresh()->description);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_update_target_fallback_does_not_guess_deleted_missing_or_other_model_accounts(): void
    {
        $admin = $this->actor('super_admin');
        $deleted = $this->account(['student']);
        $deletedId = $deleted->id;
        $deleted->delete();
        foreach ([[User::class, $deletedId], [null, null], [Submission::class, $admin->id]] as [$type, $id]) {
            AuditLog::create([
                'user_id' => $admin->id, 'module' => 'Users', 'action' => 'UPDATE',
                'description' => 'Updated student account details (Status: active)',
                'auditable_type' => $type, 'auditable_id' => $id, 'created_at' => now(),
            ]);
        }
        $this->signInAccount($admin)->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'UPDATE']))
            ->assertOk()->assertDontSee('Current linked account:')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_audit_feature_change_discards_unrelated_action_and_pagination_but_keeps_other_filters(): void
    {
        $admin = $this->actor('super_admin');
        foreach (range(1, 26) as $number) {
            AuditLog::create([
                'user_id' => $admin->id, 'user_role' => 'super_admin', 'module' => 'Auth', 'action' => 'LOGIN',
                'description' => "Target event {$number}", 'created_at' => now(),
            ]);
        }
        AuditLog::create([
            'user_id' => $admin->id, 'module' => 'Assessments', 'action' => 'SUBMIT',
            'description' => 'Other feature', 'created_at' => now(),
        ]);
        $filters = [
            'module' => 'Auth', 'action' => 'SUBMIT', 'page' => 9, 'role' => 'super_admin',
            'search' => 'Target', 'from' => now()->toDateString(), 'to' => now()->toDateString(),
        ];
        $this->signInAccount($admin)->get(route('super-admin.audit-logs', $filters))
            ->assertOk()->assertViewHas('actions', fn ($actions) => $actions->all() === ['LOGIN'])
            ->assertViewHas('logs', function ($logs) use ($filters) {
                parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $nextPage);

                return $logs->total() === 26 && $logs->currentPage() === 1
                    && ! isset($nextPage['action']) && $nextPage['page'] === '2'
                    && $nextPage['module'] === 'Auth' && $nextPage['role'] === $filters['role']
                    && $nextPage['search'] === $filters['search'] && $nextPage['from'] === $filters['from']
                    && $nextPage['to'] === $filters['to'];
            });
        $this->get(route('super-admin.audit-logs', ['module' => 'Assessments', 'action' => 'SUBMIT']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->action === 'SUBMIT');
    }

    public function test_historical_submission_displays_linked_student_without_ip_or_historical_subtitle(): void
    {
        $admin = $this->actor('super_admin');
        $student = $this->account(['student']);
        $student->update(['first_name' => 'Submission', 'last_name' => 'Student']);
        $log = AuditLog::create([
            'user_id' => $student->id, 'user_role' => 'student',
            'action' => 'SUBMIT', 'module' => 'Assessments', 'description' => "Submitted attempt for 'Quiz 2'",
            'auditable_type' => Submission::class, 'auditable_id' => 123,
            'user_agent' => 'System Migration', 'ip_address' => '127.0.0.1', 'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin',
            'action' => 'LOGIN', 'module' => 'Auth', 'description' => 'User logged in successfully.',
            'ip_address' => '198.51.100.42', 'created_at' => now(),
        ]);

        $this->signInAccount($admin)->get(route('super-admin.audit-logs'))
            ->assertOk()->assertSee('Submission Student')->assertSee($student->email)
            ->assertDontSee('System Migration')->assertDontSee('Historical audit log')
            ->assertDontSee('IP Address')->assertDontSee('127.0.0.1')->assertDontSee('198.51.100.42');
        $this->assertSame('Student', $log->userRoleLabel());
        $this->assertDatabaseHas('audit_logs', [
            'audit_log_id' => $log->getKey(), 'user_id' => $student->id,
            'user_agent' => 'System Migration', 'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_historical_actor_fallback_does_not_attribute_account_creation_to_its_owner(): void
    {
        $admin = $this->actor('super_admin');
        $missingStudent = AuditLog::create([
            'user_id' => null, 'user_role' => 'student', 'action' => 'SUBMIT', 'module' => 'Assessments',
            'description' => 'Submitted attempt with missing student reference',
            'auditable_type' => Submission::class, 'auditable_id' => 123,
            'user_agent' => 'System Migration', 'created_at' => now(),
        ]);
        $accountCreation = AuditLog::create([
            'user_id' => $admin->id, 'user_role' => 'super_admin', 'action' => 'CREATE', 'module' => 'Users',
            'description' => 'Historical account creation', 'auditable_type' => User::class,
            'auditable_id' => $admin->id, 'user_agent' => 'System Migration', 'created_at' => now(),
        ]);

        $this->signInAccount($admin)->get(route('super-admin.audit-logs', ['action' => 'SUBMIT']))
            ->assertOk()->assertSee('Student identity unavailable')->assertDontSee('Historical audit log');
        $this->assertSame('Student', $missingStudent->userRoleLabel());
        $this->assertSame('', $missingStudent->actorSubtitle());
        $this->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'CREATE']))
            ->assertOk()->assertSee('Creator unavailable')->assertSee('Unknown')->assertDontSee('System Migration');
        $this->assertSame('Creator unavailable', $accountCreation->actorName());
        $this->assertSame('Unknown', $accountCreation->userRoleLabel());
        $this->assertSame('', $accountCreation->actorSubtitle());
    }
}
