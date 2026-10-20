<?php

namespace Tests\Feature;

use App\Http\Controllers\Instructor\ReportController;
use App\Models\Assessment;
use App\Models\InstructorProfile;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportAutosaveTest extends TestCase
{
    private User $instructor;

    private PublishAssessment $published;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'hashing.bcrypt.rounds' => 4]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach ([
            'users' => 'id INTEGER PRIMARY KEY, public_id TEXT, first_name TEXT, last_name TEXT, name TEXT, email TEXT, password TEXT, status TEXT, must_change_password INTEGER DEFAULT 0, session_version INTEGER DEFAULT 0, remember_token TEXT, created_at DATETIME, updated_at DATETIME',
            'roles' => 'role_id INTEGER PRIMARY KEY, role_name TEXT, created_at DATETIME, updated_at DATETIME',
            'user_roles' => 'user_id INTEGER, role_id INTEGER, created_at DATETIME, updated_at DATETIME',
            'instructor_profiles' => 'instructor_profile_id INTEGER PRIMARY KEY, user_id INTEGER, department_id INTEGER, created_at DATETIME, updated_at DATETIME',
            'departments' => 'department_id INTEGER PRIMARY KEY, college_id INTEGER',
            'assessments' => 'assessment_id INTEGER PRIMARY KEY, public_id TEXT, instructor_id INTEGER, subject_id INTEGER, title TEXT, report_category TEXT, created_at DATETIME, updated_at DATETIME',
            'publish_assessment' => 'publish_assessment_id INTEGER PRIMARY KEY, public_id TEXT, assessment_id INTEGER, class_details_id INTEGER, publish_status TEXT, due_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'submissions' => 'submission_id INTEGER PRIMARY KEY, publish_assessment_id INTEGER',
            'assessment_items' => 'assessment_item_id INTEGER PRIMARY KEY, assessment_id INTEGER, sort_order INTEGER',
            'app_notifications' => 'notification_id INTEGER PRIMARY KEY, public_id TEXT, user_id INTEGER, title TEXT, message TEXT, type TEXT, link TEXT, read_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'reports' => 'report_id INTEGER PRIMARY KEY, publish_assessment_id INTEGER, report_type TEXT, course_code_title TEXT, concept_most_learned_skills TEXT, concept_least_learned_skills TEXT, issues_concern TEXT, interventions_done TEXT, future_plans_curriculum TEXT, report_status TEXT, created_at DATETIME, updated_at DATETIME',
        ] as $table => $columns) {
            DB::statement("CREATE TABLE {$table} ({$columns})");
        }
        DB::statement('CREATE UNIQUE INDEX reports_publish_assessment_id_report_type_unique ON reports (publish_assessment_id, report_type)');
        (require database_path('migrations/2026_10_17_000001_add_revision_to_reports_table.php'))->up();
        (require database_path('migrations/2026_10_21_000001_expand_report_course_code_title.php'))->up();
        $this->createAuditTables();
        $this->instructor = $this->account();
        $this->published = $this->assessmentFor($this->instructor);
        $this->signIn($this->instructor);
    }

    public function test_autosave_persists_draft_and_returns_new_revision_without_finalizing(): void
    {
        $body = $this->payload();
        $body['save_action'] = 'finalized'; // The autosave endpoint must never finalize.
        $response = $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $report = $this->published->report()->firstOrFail();
        $this->assertSame('Draft text', $report->issues_concern);
        $this->assertSame('draft', $report->report_status);
        $this->assertSame(1, $report->revision);
        $response->assertJsonPath('revisions.'.$this->published->public_id, 1);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        // A reload or another signed-in device reads persisted content, independent of browser storage.
        $this->assertSame('Draft text', Report::findOrFail($report->report_id)->issues_concern);
        $body['revisions'][$this->published->public_id] = 1;
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk()
            ->assertJsonPath('revisions.'.$this->published->public_id, 1); // Unchanged saves are no-ops.
    }

    public static function combinedReportSaves(): array
    {
        return [
            'formative autosave' => ['formative', 'autosave'],
            'summative autosave' => ['summative', 'autosave'],
            'formative draft' => ['formative', 'draft'],
            'summative finalize' => ['summative', 'finalized'],
        ];
    }

    public function test_course_title_migration_preserves_values_and_refuses_a_lossy_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_21_000001_expand_report_course_code_title.php');
        $report = $this->published->report()->firstOrFail();
        $report->update(['course_code_title' => 'Existing course title']);
        $migration->down();
        $migration->up();
        $this->assertSame('Existing course title', $report->fresh()->course_code_title);

        $title = str_repeat('Combined course title | ', 20);
        $report->update(['course_code_title' => $title]);
        try {
            $migration->down();
            $this->fail('Rollback must not truncate existing course titles.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('exceed 255 characters', $exception->getMessage());
        }
        $this->assertSame($title, $report->fresh()->course_code_title);
    }

    #[DataProvider('combinedReportSaves')]
    public function test_six_subjects_keep_their_own_titles_when_combined_separated_and_recombined(string $type, string $action): void
    {
        DB::statement('CREATE TABLE subjects (subject_id INTEGER PRIMARY KEY, subject_code TEXT, subject_name TEXT)');
        $assessments = new \Illuminate\Database\Eloquent\Collection([$this->published]);
        for ($i = 1; $i < 6; $i++) {
            $assessments->push($this->assessmentFor($this->instructor));
        }
        $body = ['report_type' => $type, 'publish_assessment_keys' => [], 'reports' => [], 'revisions' => []];
        foreach ($assessments as $index => $published) {
            $subjectId = DB::table('subjects')->insertGetId([
                'subject_code' => 'IT-'.($index + 1),
                'subject_name' => 'Information Technology and Computing Course Number '.($index + 1),
            ]);
            $published->assessment()->update(['subject_id' => $subjectId, 'report_category' => $type]);
            $published->report()->update(['report_type' => $type]);
            $body['publish_assessment_keys'][] = $published->public_id;
            $body['reports'][$published->public_id] = ['issues_concern' => 'Notes for subject '.($index + 1)];
            $body['revisions'][$published->public_id] = 0;
        }
        $assessments->load('assessment.subject', 'classDetail');
        $method = new \ReflectionMethod(ReportController::class, 'defaultCourseCodeTitle');
        $body['course_code_title'] = $method->invoke(app(ReportController::class), $assessments);
        $this->assertGreaterThan(255, mb_strlen($body['course_code_title']));
        if ($action === 'autosave') {
            $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        } else {
            $this->post(route('instructor.reports.save'), $body + ['save_action' => $action])->assertSessionHasNoErrors()->assertRedirect();
        }
        foreach ($assessments as $index => $published) {
            $report = $published->report()->firstOrFail();
            $ownTitle = $method->invoke(app(ReportController::class), collect([$published]));
            $this->assertSame($ownTitle, $report->course_code_title);
            $this->assertSame('Notes for subject '.($index + 1), $report->issues_concern);
            $this->assertSame($action === 'finalized' ? 'finalized' : 'draft', $report->report_status);
            $this->assertSame(1, $report->revision);
            $this->get(route('instructor.reports.build', ['type' => $type, 'publish_assessment_keys' => [$published->public_id]]))
                ->assertOk()->assertViewHas('reportMeta', fn ($meta) => $meta['course_code_title'] === $ownTitle);
            if ($action === 'autosave') {
                $single = [
                    'report_type' => $type, 'course_code_title' => $body['course_code_title'],
                    'publish_assessment_keys' => [$published->public_id],
                    'reports' => [$published->public_id => $body['reports'][$published->public_id]],
                    'revisions' => [$published->public_id => 1],
                ];
                $this->postJson(route('instructor.reports.autosave'), $single)->assertOk()
                    ->assertJsonPath('revisions.'.$published->public_id, 1);
            }
        }
        $this->get(route('instructor.reports.build', ['type' => $type, 'publish_assessment_keys' => $body['publish_assessment_keys']]))
            ->assertOk()->assertViewHas('reportMeta', fn ($meta) => $meta['course_code_title'] === $body['course_code_title']);
        if ($action === 'autosave') {
            $body['revisions'] = array_fill_keys($body['publish_assessment_keys'], 1);
            $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
            $this->assertSame(6, Report::query()->where('revision', 1)->count(), 'Changing the selection must not rewrite every report title.');
        }
    }

    public function test_legacy_combined_titles_are_ignored_when_opening_a_single_report_in_both_portals(): void
    {
        DB::statement('CREATE TABLE subjects (subject_id INTEGER PRIMARY KEY, subject_code TEXT, subject_name TEXT)');
        $subjectId = DB::table('subjects')->insertGetId(['subject_code' => 'IC1', 'subject_name' => 'Personal Development']);
        $this->published->assessment()->update(['subject_id' => $subjectId]);
        $legacyTitle = 'IC1 / Personal Development | ELEC2 / Mobile Application Development';
        $this->published->report()->update(['course_code_title' => $legacyTitle, 'issues_concern' => 'Existing report notes']);
        $this->withSession(['_old_input' => ['course_code_title' => $legacyTitle]])
            ->get(route('instructor.reports.build', ['type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id]]))
            ->assertOk()->assertViewHas('reportMeta', fn ($meta) => $meta['course_code_title'] === 'IC1 / Personal Development')
            ->assertSee('value="IC1 / Personal Development" readonly', false)
            ->assertSee('>Existing report notes</textarea>', false)
            ->assertDontSee($legacyTitle);

        $this->published->load('assessment.subject', 'classDetail');
        $rows = collect([['report' => $this->published->report()->first(), 'analytics' => ['students_count' => 0]]]);
        $chair = app(\App\Http\Controllers\DepartmentChair\ReportController::class);
        $method = new \ReflectionMethod($chair, 'reportSheetMeta');
        $this->assertSame('IC1 / Personal Development', $method->invoke($chair, collect([$this->published]), $rows)['course_code_title']);
        $this->assertSame($legacyTitle, $this->published->report()->first()->course_code_title, 'Opening an existing report must not silently rewrite database records.');
    }

    public function test_stale_tab_cannot_overwrite_through_autosave_or_manual_finalize(): void
    {
        $stale = $this->payload();
        $this->postJson(route('instructor.reports.autosave'), $stale)->assertOk();
        $stale['reports'][$this->published->public_id]['issues_concern'] = 'Stale replacement';
        $this->postJson(route('instructor.reports.autosave'), $stale)->assertStatus(409)->assertJsonPath('code', 'report_conflict');
        $this->postJson(route('instructor.reports.save'), $stale + ['save_action' => 'finalized'])
            ->assertUnprocessable()->assertJsonValidationErrors('report_conflict');
        $this->assertSame('Draft text', $this->published->report()->first()->issues_concern);
        $this->assertSame('draft', $this->published->report()->first()->report_status);
    }

    public function test_fresh_report_page_displays_server_saved_content_and_matching_revision(): void
    {
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertOk();
        $this->get(route('instructor.reports.build', [
            'type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk()->assertSee('>Draft text</textarea>', false)
            ->assertSee('data-report-revision="'.$this->published->public_id.'" value="1"', false)
            ->assertSee('data-report-autosave-enabled="true"', false);
    }

    public function test_finalized_report_cannot_be_autosaved_but_explicit_manual_edit_remains_supported(): void
    {
        $body = $this->payload();
        $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])->assertRedirect();
        $body['revisions'][$this->published->public_id] = 1;
        $body['reports'][$this->published->public_id]['issues_concern'] = 'Deliberate manual edit';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertStatus(409);
        $this->assertSame('Draft text', $this->published->report()->first()->issues_concern);
        $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])->assertRedirect();
        $this->assertSame('Deliberate manual edit', $this->published->report()->first()->issues_concern);
        $this->assertSame('finalized', $this->published->report()->first()->report_status);
        $this->assertSame(2, $this->published->report()->first()->revision);
    }

    public function test_conflict_in_one_row_rolls_back_the_entire_sheet(): void
    {
        $second = $this->assessmentFor($this->instructor);
        $body = $this->payload();
        $body['publish_assessment_keys'][] = $second->public_id;
        $body['reports'][$second->public_id] = ['issues_concern' => 'Second text'];
        $body['revisions'][$second->public_id] = 0;
        $second->report()->first()->update(['report_status' => 'finalized']);
        $this->postJson(route('instructor.reports.autosave'), $body)->assertStatus(409);
        $this->assertNull($this->published->report()->first()->issues_concern);
        $this->assertSame(0, $this->published->report()->first()->revision);
    }

    public function test_manual_conflict_redirect_preserves_unsaved_text_and_stale_revision_until_reload(): void
    {
        $body = $this->payload();
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $body['save_action'] = 'finalized';
        $body['reports'][$this->published->public_id]['issues_concern'] = 'Keep this stale text';
        $url = route('instructor.reports.build', ['type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id]]);
        $this->from($url)->post(route('instructor.reports.save'), $body)->assertRedirect($url)
            ->assertSessionHasErrors('report_conflict')
            ->assertSessionHasInput('revisions.'.$this->published->public_id, 0);
        $this->get($url)->assertOk()->assertSee('>Keep this stale text</textarea>', false)
            ->assertSee('data-report-conflict="true"', false)
            ->assertSee('data-report-revision="'.$this->published->public_id.'" value="0"', false);
        $this->assertSame('Draft text', $this->published->report()->first()->issues_concern);
    }

    public function test_other_instructors_assessments_incomplete_assessments_and_wrong_type_are_rejected(): void
    {
        $other = $this->assessmentFor($this->account());
        $body = $this->payload();
        $body['publish_assessment_keys'] = [$other->public_id];
        $body['reports'] = [$other->public_id => ['issues_concern' => 'Unauthorized']];
        $body['revisions'] = [$other->public_id => 0];
        $this->postJson(route('instructor.reports.autosave'), $body)->assertUnprocessable();
        $this->published->update(['publish_status' => 'published', 'due_at' => now()->addDay()]);
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertUnprocessable();
        $this->published->update(['publish_status' => 'closed']);
        $body = $this->payload();
        $body['report_type'] = 'summative';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertUnprocessable();
        $this->assertSame(0, Report::query()->whereNotNull('issues_concern')->count());
    }

    public function test_missing_invalid_or_incomplete_revision_payload_cannot_clear_report_rows(): void
    {
        $body = $this->payload();
        unset($body['revisions']);
        $this->postJson(route('instructor.reports.autosave'), $body)->assertUnprocessable()->assertJsonValidationErrors('revisions');
        $body = $this->payload();
        $body['revisions'][$this->published->public_id] = -1;
        $this->postJson(route('instructor.reports.autosave'), $body)->assertUnprocessable();
        $body = $this->payload();
        $body['reports'] = ['another-key' => ['issues_concern' => 'Other']];
        $this->postJson(route('instructor.reports.autosave'), $body)->assertUnprocessable()->assertJsonValidationErrors('reports');
        $this->assertSame(0, $this->published->report()->first()->revision);
    }

    public function test_summative_saves_future_plans_and_clears_inapplicable_interventions(): void
    {
        $this->published->assessment->update(['report_category' => 'summative']);
        $this->published->report()->first()->update(['report_type' => 'summative']);
        $body = $this->payload();
        $body['report_type'] = 'summative';
        $body['reports'][$this->published->public_id] += ['future_plans_curriculum' => 'Future plans', 'interventions_done' => 'Wrong column'];
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $report = $this->published->report()->first();
        $this->assertSame('Future plans', $report->future_plans_curriculum);
        $this->assertNull($report->interventions_done);
    }

    public function test_guest_student_inactive_and_first_login_accounts_cannot_autosave(): void
    {
        Auth::logout();
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertUnauthorized();
        $this->signIn($this->account('student'));
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertForbidden();
        $this->instructor->update(['status' => 'inactive']);
        $this->signIn($this->instructor);
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertForbidden();
        $this->instructor->update(['status' => 'active', 'must_change_password' => true]);
        $this->signIn($this->instructor);
        $this->postJson(route('instructor.reports.autosave'), $this->payload())->assertForbidden();
        $this->assertSame(0, $this->published->report()->first()->revision);
    }

    public function test_rate_limit_does_not_consume_manual_save_budget(): void
    {
        $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        for ($index = 0; $index < 60; $index++) {
            $this->postJson(route('instructor.reports.autosave'), [])->assertUnprocessable();
        }
        $this->postJson(route('instructor.reports.autosave'), [])->assertStatus(429);
        $this->post(route('instructor.reports.save'), $this->payload() + ['save_action' => 'draft'])->assertRedirect();
        $this->assertSame(1, $this->published->report()->first()->revision);
    }

    private function payload(): array
    {
        return [
            'report_type' => 'formative', 'course_code_title' => 'Test subject',
            'publish_assessment_keys' => [$this->published->public_id],
            'reports' => [$this->published->public_id => ['issues_concern' => 'Draft text']],
            'revisions' => [$this->published->public_id => 0],
        ];
    }

    private function account(string $role = 'instructor'): User
    {
        $user = User::create(['first_name' => 'Report', 'last_name' => 'Teacher', 'email' => uniqid().'@example.test', 'password' => 'Testing-password-1', 'status' => 'active', 'must_change_password' => false]);
        $user->roles()->attach(Role::firstOrCreate(['role_name' => $role]));
        InstructorProfile::create(['user_id' => $user->id]);

        return $user;
    }

    private function assessmentFor(User $user): PublishAssessment
    {
        $assessment = Assessment::create(['instructor_id' => $user->instructorProfile->instructor_profile_id, 'title' => 'Test quiz', 'report_category' => 'formative']);
        $published = PublishAssessment::create(['assessment_id' => $assessment->assessment_id, 'publish_status' => 'closed']);
        Report::create(['publish_assessment_id' => $published->publish_assessment_id, 'report_type' => 'formative', 'report_status' => 'draft']);

        return $published;
    }

    private function signIn(User $user): void
    {
        $this->withSession(['account_session_version' => $user->session_version, 'password_hash_web' => Auth::guard('web')->hashPasswordForCookie($user->password)]);
        $this->actingAs($user);
    }
}
