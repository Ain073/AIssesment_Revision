<?php

namespace Tests\Feature;

use App\Http\Controllers\Instructor\ReportController;
use App\Models\Assessment;
use App\Models\InstructorProfile;
use App\Models\PublishAssessment;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportAiService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'submissions' => 'submission_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, publish_assessment_id INTEGER',
            'assessment_items' => 'assessment_item_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, sort_order INTEGER',
            'app_notifications' => 'notification_id INTEGER PRIMARY KEY, public_id TEXT, user_id INTEGER, title TEXT, message TEXT, type TEXT, link TEXT, read_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'reports' => 'report_id INTEGER PRIMARY KEY, publish_assessment_id INTEGER, report_type TEXT, course_code_title TEXT, concept_most_learned_skills TEXT, concept_least_learned_skills TEXT, issues_concern TEXT, interventions_done TEXT, future_plans_curriculum TEXT, report_status TEXT, created_at DATETIME, updated_at DATETIME',
        ] as $table => $columns) {
            DB::statement("CREATE TABLE {$table} ({$columns})");
        }
        DB::statement('CREATE UNIQUE INDEX reports_publish_assessment_id_report_type_unique ON reports (publish_assessment_id, report_type)');
        (require database_path('migrations/2026_10_17_000001_add_revision_to_reports_table.php'))->up();
        (require database_path('migrations/2026_10_21_000001_expand_report_course_code_title.php'))->up();
        (require database_path('migrations/2026_10_24_000001_add_column_headers_to_reports_table.php'))->up();
        (require database_path('migrations/2026_10_25_000001_add_branding_to_reports_table.php'))->up();
        (require database_path('migrations/2026_10_25_000002_add_document_control_to_reports_table.php'))->up();
        (require database_path('migrations/2026_10_25_000003_add_signature_names_to_reports_table.php'))->up();
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

    public static function editableReportTypes(): array
    {
        return ['formative draft' => ['formative', 'draft'], 'summative final' => ['summative', 'finalized']];
    }

    #[DataProvider('editableReportTypes')]
    public function test_all_column_headers_save_and_reload_for_each_report_type(string $type, string $action): void
    {
        if ($type === 'summative') {
            $this->published->assessment()->update(['report_category' => $type]);
            $this->published->report()->update(['report_type' => $type]);
        }

        $labels = array_replace(config('reports.columns'), config('reports.'.$type, []));
        foreach (array_keys($labels) as $key) {
            $labels[$key] = 'Custom '.strtoupper($key).' heading';
        }
        $body = $this->payload();
        $body['report_type'] = $type;
        $body['column_headers'] = $labels;
        $this->post(route('instructor.reports.save'), $body + ['save_action' => $action])
            ->assertSessionHasNoErrors()->assertRedirect();

        $report = $this->published->report()->firstOrFail();
        $this->assertSame($labels, $report->column_headers);
        $this->assertSame(1, $report->revision);
        $page = $this->get(route('instructor.reports.build', [
            'type' => $type, 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk();
        foreach ($labels as $key => $label) {
            $page->assertSee('name="column_headers['.$key.']"', false)
                ->assertSee('>'.$label.'</textarea>', false)
                ->assertSee('data-print-field-name="column_headers['.$key.']"', false);
        }
        $this->assertSame('Draft text', $report->issues_concern);

        $body['revisions'][$this->published->public_id] = 1;
        $body['column_headers']['most'] = 'Revised most learned heading';
        if ($action === 'draft') {
            $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        } else {
            $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame('Revised most learned heading', $report->fresh()->column_headers['most']);
        $this->assertSame(2, $report->fresh()->revision);
    }

    public function test_combined_sheet_only_replaces_other_reports_headers_when_header_text_changes(): void
    {
        $second = $this->assessmentFor($this->instructor);
        $defaults = array_replace(config('reports.columns'), config('reports.formative'));
        $secondLabels = array_replace($defaults, ['issues' => 'Previously customized']);
        $second->report()->firstOrFail()->update(['column_headers' => $secondLabels]);

        $body = $this->payload();
        $body['publish_assessment_keys'][] = $second->public_id;
        $body['reports'][$second->public_id] = ['issues_concern' => 'Second paragraph'];
        $body['revisions'][$second->public_id] = 0;
        $body['column_headers'] = $defaults;
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame($secondLabels, $second->report()->first()->column_headers);

        $body['revisions'][$this->published->public_id] = 1;
        $body['revisions'][$second->public_id] = 1;
        $body['column_headers']['issues'] = 'Shared updated heading';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame($body['column_headers'], $this->published->report()->first()->column_headers);
        $this->assertSame($body['column_headers'], $second->report()->first()->column_headers);
    }

    public function test_invalid_column_headers_cannot_replace_saved_labels(): void
    {
        $body = $this->payload();
        $body['column_headers'] = array_replace(config('reports.columns'), config('reports.formative'));
        $body['column_headers']['items'] = str_repeat('x', 121);
        $this->postJson(route('instructor.reports.autosave'), $body)
            ->assertUnprocessable()->assertJsonValidationErrors('column_headers.items');
        $this->assertNull($this->published->report()->first()->column_headers);

        unset($body['column_headers']['items']);
        $this->postJson(route('instructor.reports.autosave'), $body)
            ->assertUnprocessable()->assertJsonValidationErrors('column_headers');
        $this->assertSame(0, $this->published->report()->first()->revision);
    }

    #[DataProvider('editableReportTypes')]
    public function test_campus_is_saved_and_shown_in_report_and_print_views(string $type, string $action): void
    {
        if ($type === 'summative') {
            $this->published->assessment()->update(['report_category' => $type]);
            $this->published->report()->update(['report_type' => $type]);
        }

        $body = $this->payload();
        $body['report_type'] = $type;
        $body['campus'] = 'Urdaneta';
        $this->post(route('instructor.reports.save'), $body + ['save_action' => $action])
            ->assertSessionHasNoErrors()->assertRedirect();
        $report = $this->published->report()->firstOrFail();
        $this->assertSame('Urdaneta', $report->campus);

        $this->get(route('instructor.reports.build', [
            'type' => $type, 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk()->assertViewHas('reportMeta', fn ($meta) => $meta['campus'] === 'Urdaneta')
            ->assertSee('name="campus"', false)
            ->assertSee('data-print-field-name="campus"', false)
            ->assertSee('URDANETA');

        $body['revisions'][$this->published->public_id] = 1;
        $body['campus'] = 'Lingayen';
        if ($action === 'draft') {
            $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        } else {
            $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame('Lingayen', $report->fresh()->campus);
    }

    public function test_combined_campus_change_does_not_overwrite_other_reports_without_an_edit(): void
    {
        $second = $this->assessmentFor($this->instructor);
        $second->report()->firstOrFail()->update(['campus' => 'Urdaneta']);
        $body = $this->payload();
        $body['publish_assessment_keys'][] = $second->public_id;
        $body['reports'][$second->public_id] = ['issues_concern' => 'Second note'];
        $body['revisions'][$second->public_id] = 0;
        $body['campus'] = 'SAN CARLOS';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame('Urdaneta', $second->report()->first()->campus);

        $body['revisions'] = array_fill_keys($body['publish_assessment_keys'], 1);
        $body['campus'] = 'Lingayen';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame('Lingayen', $second->report()->first()->campus);
        $this->assertSame('Lingayen', $this->published->report()->first()->campus);
    }

    #[DataProvider('editableReportTypes')]
    public function test_document_control_saves_and_reloads_in_both_report_types(string $type, string $action): void
    {
        if ($type === 'summative') {
            $this->published->assessment()->update(['report_category' => $type]);
            $this->published->report()->update(['report_type' => $type]);
        }

        $details = [
            'document_code' => 'FM-AA-CIA-20a',
            'document_revision' => 'Rev. 0',
            'document_date' => '30-Oct-2024',
        ];
        $body = $this->payload() + $details;
        $body['report_type'] = $type;
        $this->post(route('instructor.reports.save'), $body + ['save_action' => $action])
            ->assertSessionHasNoErrors()->assertRedirect();
        $report = $this->published->report()->firstOrFail();
        foreach ($details as $field => $value) {
            $this->assertSame($value, $report->$field);
        }

        $page = $this->get(route('instructor.reports.build', [
            'type' => $type, 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk();
        foreach ($details as $field => $value) {
            $page->assertSee('name="'.$field.'"', false)
                ->assertSee('value="'.$value.'"', false)
                ->assertSee('<span class="report-print-text">'.$value.'</span>', false);
        }
        $page->assertSeeInOrder(['FM-AA-CIA-20a', 'Rev. 0', '30-Oct-2024']);

        $body['revisions'][$this->published->public_id] = 1;
        $body['document_date'] = '25-Oct-2026';
        if ($action === 'draft') {
            $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        } else {
            $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame('25-Oct-2026', $report->fresh()->document_date);
    }

    public function test_combined_sheet_preserves_other_document_details_until_they_are_edited_or_cleared(): void
    {
        $second = $this->assessmentFor($this->instructor);
        $second->report()->firstOrFail()->update(['document_code' => 'OTHER-FORM']);
        $body = $this->payload();
        $body['publish_assessment_keys'][] = $second->public_id;
        $body['reports'][$second->public_id] = ['issues_concern' => 'Second note'];
        $body['revisions'][$second->public_id] = 0;
        $body['document_code'] = null;
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame('OTHER-FORM', $second->report()->first()->document_code);

        $body['revisions'] = array_fill_keys($body['publish_assessment_keys'], 1);
        $body['document_code'] = 'CURRENT-FORM';
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertSame('CURRENT-FORM', $second->report()->first()->document_code);

        $body['revisions'] = array_fill_keys($body['publish_assessment_keys'], 2);
        $body['document_code'] = null;
        $this->postJson(route('instructor.reports.autosave'), $body)->assertOk();
        $this->assertNull($second->report()->first()->document_code);
        $this->assertNull($this->published->report()->first()->document_code);
    }

    public function test_invalid_document_control_text_cannot_overwrite_a_saved_value(): void
    {
        $this->published->report()->firstOrFail()->update(['document_code' => 'EXISTING']);
        $body = $this->payload();
        $body['document_code'] = str_repeat('X', 81);
        $this->postJson(route('instructor.reports.autosave'), $body)
            ->assertUnprocessable()->assertJsonValidationErrors('document_code');
        $this->assertSame('EXISTING', $this->published->report()->first()->document_code);
    }

    public function test_logo_upload_updates_selected_reports_and_keeps_other_reports_unchanged(): void
    {
        config(['reports.logo_disk' => 'public']);
        Storage::fake('public');
        $second = $this->assessmentFor($this->instructor);
        $other = $this->assessmentFor($this->instructor);
        $body = [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$this->published->public_id, $second->public_id],
            'logo' => $this->logo(),
        ];
        $this->post(route('instructor.reports.logo'), $body)->assertSessionHasNoErrors()->assertRedirect();
        $path = $this->published->report()->firstOrFail()->logo_path;
        $this->assertSame($path, $second->report()->firstOrFail()->logo_path);
        $this->assertNull($other->report()->firstOrFail()->logo_path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(1, $this->published->report()->first()->revision);

        $page = $this->get(route('instructor.reports.build', [
            'type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk();
        $page->assertSee('name="logo"', false)
            ->assertSee('id="editReportLogoButton"', false)
            ->assertSee('data-bs-target="#reportLogoModal"', false)
            ->assertSee('id="reportLogoModal"', false)
            ->assertSee($this->published->report()->first()->logoUrl());

        $this->post(route('instructor.reports.logo'), [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$this->published->public_id],
            'logo' => $this->logo(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('public')->assertExists($path); // Still used by the second report.

        $this->post(route('instructor.reports.logo'), [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$second->public_id],
            'logo' => $this->logo(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_logo_upload_rejects_invalid_file_and_another_instructors_report(): void
    {
        config(['reports.logo_disk' => 'public']);
        Storage::fake('public');
        $this->withHeaders(['Accept' => 'application/json'])->post(route('instructor.reports.logo'), [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$this->published->public_id],
            'logo' => UploadedFile::fake()->create('malware.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('logo');

        $other = $this->assessmentFor($this->account());
        $this->post(route('instructor.reports.logo'), [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$other->public_id],
            'logo' => $this->logo(),
        ])->assertUnprocessable()->assertJsonValidationErrors('publish_assessment_keys');
        $this->assertNull($other->report()->first()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles('report-logos'));
    }

    public function test_logo_upload_uses_configured_cloud_storage(): void
    {
        config(['reports.logo_disk' => 's3']);
        Storage::fake('s3');
        $this->post(route('instructor.reports.logo'), [
            'report_type' => 'formative',
            'publish_assessment_keys' => [$this->published->public_id],
            'logo' => $this->logo(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $path = $this->published->report()->firstOrFail()->logo_path;
        $this->assertStringStartsWith('s3:report-logos/', $path);
        Storage::disk('s3')->assertExists(substr($path, 3));
        $this->assertSame(Storage::disk('s3')->url(substr($path, 3)), $this->published->report()->first()->logoUrl());
    }

    public function test_chair_report_metadata_reads_saved_campus_without_changing_report(): void
    {
        $report = $this->published->report()->firstOrFail();
        $report->update(['campus' => 'Alaminos', 'report_status' => 'finalized']);
        $this->published->load('assessment.subject', 'classDetail');
        $rows = collect([['report' => $report, 'analytics' => ['students_count' => 0]]]);
        $chair = app(\App\Http\Controllers\DepartmentChair\ReportController::class);
        $method = new \ReflectionMethod($chair, 'reportSheetMeta');
        $this->assertSame('Alaminos', $method->invoke($chair, collect([$this->published]), $rows)['campus']);
        $this->assertSame('Alaminos', $report->fresh()->campus);
    }

    #[DataProvider('editableReportTypes')]
    public function test_signatures_use_the_report_owner_and_same_department_chair_then_keep_finalized_names(string $type): void
    {
        $this->instructor->instructorProfile()->update(['department_id' => 1]);
        $chair = $this->account('department_chair');
        $chair->update(['first_name' => 'Alice', 'last_name' => 'Chair']);
        $chair->instructorProfile()->update(['department_id' => 1]);
        $otherChair = $this->account('department_chair');
        $otherChair->update(['first_name' => 'Bob', 'last_name' => 'Other']);
        $otherChair->instructorProfile()->update(['department_id' => 2]);

        if ($type === 'summative') {
            $this->published->assessment()->update(['report_category' => $type]);
            $this->published->report()->update(['report_type' => $type]);
        }

        $url = route('instructor.reports.build', [
            'type' => $type, 'publish_assessment_keys' => [$this->published->public_id],
        ]);
        $this->get($url)->assertOk()->assertSee('Report Teacher')->assertSee('Alice Chair')->assertDontSee('Bob Other');

        $body = $this->payload();
        $body['report_type'] = $type;
        $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $report = $this->published->report()->firstOrFail();
        $this->assertSame('Report Teacher', $report->prepared_by_name);
        $this->assertSame('Alice Chair', $report->reviewed_by_name);

        $chair->roles()->detach(Role::firstWhere('role_name', 'department_chair'));
        $otherChair->instructorProfile()->update(['department_id' => 1]);
        $this->instructor->update(['first_name' => 'Renamed']);
        $this->get($url)->assertOk()
            ->assertSee('<div class="report-signature-name">Report Teacher</div>', false)
            ->assertSee('<div class="report-signature-name">Alice Chair</div>', false)
            ->assertDontSee('<div class="report-signature-name">Renamed Teacher</div>', false)
            ->assertDontSee('<div class="report-signature-name">Bob Other</div>', false);
    }

    public function test_missing_chair_stays_blank_in_finalized_report_and_combined_sheet_has_one_footer(): void
    {
        $this->instructor->instructorProfile()->update(['department_id' => 1]);
        $second = $this->assessmentFor($this->instructor);
        $keys = [$this->published->public_id, $second->public_id];
        $body = $this->payload();
        $body['publish_assessment_keys'] = $keys;
        $body['reports'][$second->public_id] = ['issues_concern' => 'Second note'];
        $body['revisions'][$second->public_id] = 0;
        $this->post(route('instructor.reports.save'), $body + ['save_action' => 'finalized'])
            ->assertSessionHasNoErrors()->assertRedirect();

        foreach ([$this->published, $second] as $published) {
            $report = $published->report()->firstOrFail();
            $this->assertSame('Report Teacher', $report->prepared_by_name);
            $this->assertNull($report->reviewed_by_name);
        }

        $chair = $this->account('department_chair');
        $chair->instructorProfile()->update(['department_id' => 1]);
        $html = $this->get(route('instructor.reports.build', [
            'type' => 'formative', 'publish_assessment_keys' => $keys,
        ]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="report-signatures"'));
        $this->assertStringContainsString('Chair name not recorded', $html);
        $this->assertGreaterThan(strrpos($html, 'Second note'), strpos($html, 'class="report-signatures"'));
    }

    public function test_legacy_finalized_report_does_not_claim_the_current_chair_was_its_reviewer(): void
    {
        $this->instructor->instructorProfile()->update(['department_id' => 1]);
        $chair = $this->account('department_chair');
        $chair->update(['first_name' => 'New', 'last_name' => 'Chair']);
        $chair->instructorProfile()->update(['department_id' => 1]);
        $this->published->report()->update(['report_status' => 'finalized']);

        $html = $this->get(route('instructor.reports.build', [
            'type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('Chair name not recorded', $html);
        $this->assertStringNotContainsString('<div class="report-signature-name">New Chair</div>', $html);
    }

    public static function reportAiRequests(): array
    {
        return ['without a selector' => [[]], 'previous OpenAI page' => [['ai_provider' => 'openai']]];
    }

    #[DataProvider('reportAiRequests')]
    public function test_ai_drafts_use_openai_without_a_provider_selector_even_if_the_default_is_claude(array $providerFields): void
    {
        config(['services.ai_report.provider' => 'claude']);
        $this->get(route('instructor.reports.build', ['type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id]]))
            ->assertOk()->assertSee('Generate AI Draft')
            ->assertDontSee('AI Candidate')->assertDontSee('id="aiProvider"', false)
            ->assertDontSee('id="aiProviderControl"', false)->assertDontSee('the selected AI candidate');

        $draft = ['concepts_most_learned_skills' => 'Most learned text', 'concepts_least_learned_skills' => 'Least learned text', 'source' => 'openai'];
        $this->mock(ReportAiService::class)->shouldReceive('generate')->once()
            ->with(\Mockery::on(fn ($assessment) => $assessment->is($this->published)), 'openai')->andReturn($draft);
        $this->postJson(route('instructor.reports.ai-drafts'), [
            'report_type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id],
        ] + $providerFields)->assertOk()->assertJsonPath('drafts.'.$this->published->public_id.'.source', 'openai');
        $this->assertDatabaseHas('audit_logs', ['action' => 'GENERATE', 'module' => 'Reports', 'user_id' => $this->instructor->id]);
        $this->assertNull($this->published->report()->first()->concept_most_learned_skills);
    }

    public function test_claude_cannot_be_selected_through_a_stale_report_page_or_custom_request(): void
    {
        $this->mock(ReportAiService::class)->shouldNotReceive('generate');
        $this->postJson(route('instructor.reports.ai-drafts'), [
            'report_type' => 'formative', 'publish_assessment_keys' => [$this->published->public_id], 'ai_provider' => 'claude',
        ])->assertUnprocessable()->assertJsonValidationErrors('ai_provider');
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
        $assessments = new Collection([$this->published]);
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

    public function test_pdf_opens_saved_report_inline_and_keeps_instructor_ownership(): void
    {
        $report = $this->published->report()->firstOrFail();
        $report->update(['issues_concern' => 'Saved PDF concern', 'document_code' => 'FM-TEST-1']);
        $url = route('instructor.reports.pdf', [
            'type' => 'formative',
            'paper' => 'long',
            'publish_assessment_keys' => [$this->published->public_id],
        ]);

        $response = $this->get($url)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->signIn($this->account());
        $this->withHeaders(['Accept' => 'application/json'])->get($url)->assertUnprocessable();
    }

    public function test_pdf_renders_combined_summative_reports_with_an_uploaded_logo(): void
    {
        Storage::fake('public');
        $logo = $this->logo();
        Storage::disk('public')->put('report-logos/custom.png', file_get_contents($logo->getRealPath()));
        $assessments = [$this->published];
        foreach (range(2, 14) as $index) {
            $published = $this->assessmentFor($this->instructor);
            $published->assessment()->update(['title' => 'Test quiz '.$index]);
            $assessments[] = $published;
        }
        foreach ($assessments as $published) {
            $published->assessment()->update(['report_category' => 'summative']);
            $published->report()->update([
                'report_type' => 'summative',
                'logo_path' => 'report-logos/custom.png',
                'future_plans_curriculum' => 'Saved future plan',
                'concept_most_learned_skills' => str_repeat('Students demonstrated the target skill through the assessment. ', 3),
            ]);
        }

        $response = $this->get(route('instructor.reports.pdf', [
            'type' => 'summative',
            'paper' => 'short',
            'publish_assessment_keys' => array_map(fn ($published) => $published->public_id, $assessments),
        ]))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('summative-report.pdf', $response->headers->get('Content-Disposition'));
        $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
    }

    public function test_pdf_reads_a_private_cloud_logo_from_storage(): void
    {
        Storage::fake('s3');
        $logo = $this->logo();
        Storage::disk('s3')->put('report-logos/cloud.png', file_get_contents($logo->getRealPath()));
        $this->published->report()->update(['logo_path' => 's3:report-logos/cloud.png']);

        $response = $this->get(route('instructor.reports.pdf', [
            'type' => 'formative',
            'publish_assessment_keys' => [$this->published->public_id],
        ]))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_chair_pdf_requires_a_finalized_report_in_the_chairs_department(): void
    {
        DB::table('departments')->insert([['department_id' => 1], ['department_id' => 2]]);
        $this->instructor->instructorProfile()->update(['department_id' => 1]);
        $this->published->report()->update(['report_status' => Report::STATUS_FINALIZED]);
        $chair = $this->account('department_chair');
        $chair->instructorProfile()->update(['department_id' => 1]);
        $this->signIn($chair);
        $url = route('department-chair.reports.pdf', [
            'publishAssessment' => $this->published,
            'type' => 'formative',
        ]);

        $response = $this->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $otherChair = $this->account('department_chair');
        $otherChair->instructorProfile()->update(['department_id' => 2]);
        $this->signIn($otherChair);
        $this->get($url)->assertForbidden();
    }

    public function test_rate_limit_does_not_consume_manual_save_budget(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
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

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('report-logo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAK0lEQVR4nGMQN71AU8QwasGoBaMWjFowasGoBaMWjFowasGoBaMWmA4RCwC7i3A9YST7bwAAAABJRU5ErkJggg=='
        ));
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
