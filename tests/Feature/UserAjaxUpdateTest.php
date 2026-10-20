<?php

namespace Tests\Feature;

use App\Models\InstructorProfile;
use App\Models\College;
use App\Models\AuditLog;
use App\Models\AcademicClass;
use App\Models\ClassDetail;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\User;
use App\Models\PublishAssessment;
use App\Services\UserAccountService;
use App\Services\AccountSessionService;
use App\Services\AuditLogger;
use App\Services\UserCreationAuditRecovery;
use App\Support\RecordSearch;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAjaxUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These HTTP tests never connect to the project's configured database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status');
            $table->boolean('must_change_password')->default(false);
            $table->unsignedBigInteger('session_version')->default(0);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_name');
            $table->timestamps();
        });
        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });
        Schema::create('colleges', function (Blueprint $table) {
            $table->id('college_id');
            $table->string('college_name');
            $table->uuid('public_id')->nullable()->unique();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_18_000001_add_college_acronym_to_colleges_table.php'))->up();
        Schema::create('departments', function (Blueprint $table) {
            $table->id('department_id');
            $table->uuid('public_id')->nullable();
            $table->unsignedBigInteger('college_id');
            $table->string('dept_name');
        });
        Schema::create('programs', function (Blueprint $table) {
            $table->id('program_id');
            $table->uuid('public_id')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->string('program_name');
        });
        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id('instructor_profile_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id');
            $table->string('employee_number')->unique();
            $table->timestamps();
        });
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id('student_profile_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('program_id');
            $table->string('student_number')->unique();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('audit_log_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role')->nullable();
            $table->string('action');
            $table->string('module');
            $table->text('description');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
            $table->json('metadata')->nullable();
        });
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('classes', function (Blueprint $table) {
            $table->id('class_id');
            $table->unsignedBigInteger('program_id');
            $table->string('section_name')->nullable();
            $table->string('join_code')->nullable();
            $table->string('school_year')->nullable();
            $table->unsignedInteger('year_level')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
        Schema::create('class_details', function (Blueprint $table) {
            $table->id('class_details_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('subject_id');
            $table->string('status');
            $table->string('entry_method');
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id('subject_id');
            $table->uuid('public_id')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->string('subject_code');
            $table->string('subject_name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('semesters', function (Blueprint $table) {
            $table->id('semester_id');
            $table->string('semester_name');
            $table->boolean('is_active')->default(false);
        });

        foreach (['super_admin', 'admin_dean', 'department_chair', 'instructor', 'student'] as $role) {
            Role::create(['role_name' => $role]);
        }
        DB::table('colleges')->insert([
            ['college_id' => 1, 'college_name' => 'First College', 'public_id' => (string) Str::uuid()],
            ['college_id' => 2, 'college_name' => 'Other College', 'public_id' => (string) Str::uuid()],
        ]);
        DB::table('departments')->insert([
            ['department_id' => 1, 'college_id' => 1, 'dept_name' => 'First Department'],
            ['department_id' => 2, 'college_id' => 1, 'dept_name' => 'Second Department'],
            ['department_id' => 3, 'college_id' => 2, 'dept_name' => 'Other Department'],
        ]);
        foreach ([1, 2, 3] as $id) {
            DB::table('programs')->insert(['program_id' => $id, 'department_id' => $id, 'program_name' => "Program {$id}"]);
        }
    }

    public static function portals(): array
    {
        return [
            'super admin' => ['super-admin', 'super_admin'],
            'dean' => ['admin-dean', 'admin_dean'],
            'department chair' => ['department-chair', 'department_chair'],
        ];
    }

    public static function accountUpdates(): array
    {
        $cases = [];
        foreach (self::portals() as $name => [$portal, $role]) {
            foreach (['instructor', 'student'] as $baseRole) {
                $cases["{$name} updates {$baseRole}"] = [$portal, $role, $baseRole];
            }
        }
        return $cases;
    }

    public static function designationTypes(): array
    {
        return ['dean' => ['dean'], 'department chair' => ['chair']];
    }

    #[DataProvider('designationTypes')]
    public function test_first_designation_has_a_working_picker_and_records_the_appointment(string $type): void
    {
        $this->createDesignationTables();
        $isDean = $type === 'dean';
        $actor = $this->actor($isDean ? 'super_admin' : 'admin_dean');
        $teacher = $this->account(['instructor']);
        $outside = $this->account(['instructor'], 3);
        $page = route($isDean ? 'super-admin.roles' : 'admin-dean.designations');
        $currentList = $isDean ? 'adminDeans' : 'departmentChairs';
        $availableList = $isDean ? 'availableAdminDeanTeachers' : 'availableDepartmentChairTeachers';
        $pickerId = $isDean ? 'grantAdminDeanModal' : 'grantDepartmentChairModal';
        $confirmationId = $isDean ? 'deanConfirmModal' : 'chairConfirmModal';

        $response = $this->signInAccount($actor)->get($page, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewHas($currentList, fn ($users) => $users->isEmpty())
            ->assertViewHas($availableList, fn ($users) => $users->contains('id', $teacher->id))
            ->assertSee('data-bs-target="#'.$pickerId.'"', false)
            ->assertSee('id="'.$confirmationId.'"', false)
            ->assertSee('data-designation-candidate="'.$type.'"', false)
            ->assertSee('js/designation-picker.js', false)
            ->assertDontSee('onclick="open', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="'.$pickerId.'"'));
        if (! $isDean) {
            $response->assertViewHas($availableList, fn ($users) => ! $users->contains('id', $outside->id));
        }

        $roleName = $isDean ? 'admin_dean' : 'department_chair';
        $grantUrl = $isDean ? route('super-admin.roles.grant') : route('admin-dean.teachers.department-chair.grant', $teacher);
        $this->from($page)->post($grantUrl, $isDean ? ['user_id' => $teacher->id, 'role_name' => $roleName, 'form_mode' => 'grant_admin_dean'] : [])
            ->assertRedirect($page)->assertSessionHasNoErrors();
        $this->assertTrue($teacher->fresh()->hasRole($roleName));
        $this->assertDatabaseHas('designation_details', [
            'instructor_id' => $teacher->instructorProfile->getKey(), 'designation_id' => $isDean ? 1 : 2,
            'department_id' => 1, 'college_id' => 1, 'status' => 'active', 'designated_by' => $actor->id,
        ]);
        $this->get($page)->assertOk()->assertViewHas($currentList, fn ($users) => $users->contains('id', $teacher->id));
        if (! $isDean) {
            $this->post(route('admin-dean.teachers.department-chair.grant', $outside))->assertForbidden();
        }
        $this->assertDatabaseCount('designation_details', 1);
        $grant = AuditLog::where('action', 'DESIGNATE')->sole();
        $grantChange = collect($grant->metadata['changes'])->firstWhere('model', User::class);
        $this->assertNotContains($roleName, $grantChange['before']['roles']);
        $this->assertContains($roleName, $grantChange['after']['roles']);

        $revokeUrl = $isDean ? route('super-admin.roles.revoke') : route('admin-dean.teachers.department-chair.revoke', $teacher);
        $this->from($page)->delete($revokeUrl, $isDean ? ['user_id' => $teacher->id, 'role_name' => $roleName] : [])->assertRedirect($page);
        $revoke = AuditLog::where('action', 'REVOKE')->sole();
        $revokeChange = collect($revoke->metadata['changes'])->firstWhere('model', User::class);
        $this->assertContains($roleName, $revokeChange['before']['roles']);
        $this->assertNotContains($roleName, $revokeChange['after']['roles']);
        $this->assertSame($actor->id, $revoke->user_id);
        $this->assertFalse($teacher->fresh()->hasRole($roleName));
    }

    #[DataProvider('designationTypes')]
    public function test_designation_picker_remains_available_without_eligible_teachers(string $type): void
    {
        $this->createDesignationTables();
        $isDean = $type === 'dean';
        $this->signInAccount($this->actor($isDean ? 'super_admin' : 'admin_dean'))
            ->get(route($isDean ? 'super-admin.roles' : 'admin-dean.designations'))
            ->assertOk()
            ->assertViewHas($isDean ? 'availableAdminDeanTeachers' : 'availableDepartmentChairTeachers', fn ($users) => $users->isEmpty())
            ->assertSee('No available teacher accounts')
            ->assertSee('id="'.($isDean ? 'grantAdminDeanModal' : 'grantDepartmentChairModal').'"', false);
    }

    private function createDesignationTables(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id('designation_id');
            $table->string('designation_name');
        });
        DB::table('designations')->insert([
            ['designation_id' => 1, 'designation_name' => 'Dean'],
            ['designation_id' => 2, 'designation_name' => 'Department Chair'],
        ]);
        Schema::create('designation_details', function (Blueprint $table) {
            $table->id('designation_details_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('designation_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('college_id')->nullable();
            $table->string('academic_year');
            $table->date('effectivity_date');
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('designated_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    #[DataProvider('accountUpdates')]
    public function test_ajax_form_updates_account_and_returns_json(string $portal, string $actorRole, string $baseRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account([$baseRole]);
        $password = $target->password;

        $this->signInAccount($actor)->post(route("{$portal}.users.update", $target), [
            ...$this->payload($target),
            '_method' => 'PUT',
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['message' => 'User account updated successfully.']);

        $target->refresh();
        $this->assertSame('Updated Person', $target->name);
        $this->assertSame($password, $target->password);
        $this->assertTrue($target->hasRole($baseRole));
        if ($baseRole === 'instructor') {
            $this->assertSame(1, $target->instructorProfile->department_id);
        } else {
            $this->assertSame(1, $target->studentProfile->program_id);
        }
        if ($actorRole === 'super_admin') {
            $identifierLabel = $baseRole === 'student' ? 'Student No.' : 'Employee No.';
            $identifier = $baseRole === 'student' ? $target->studentProfile->student_number : $target->instructorProfile->employee_number;
            $description = "Updated {$baseRole} account for Updated Person ({$identifierLabel}: {$identifier}; Status: active)";
            $log = AuditLog::where('action', 'UPDATE')->sole();
            $this->assertSame($actor->id, $log->user_id);
            $this->assertSame($target->id, $log->auditable_id);
            $this->assertSame($description, $log->displayDescription());
            $target->update(['first_name' => 'Later']);
            $target->delete();
            $this->assertSame($description, $log->fresh()->displayDescription());
        }
    }

    #[DataProvider('portals')]
    public function test_invalid_ajax_update_returns_422_without_saving(string $portal, string $actorRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account(['instructor']);
        $payload = $this->payload($target);
        $payload['email'] = $actor->email;

        $this->signInAccount($actor)->putJson(route("{$portal}.users.update", $target), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_dean_and_chair_cannot_update_users_outside_their_scope(): void
    {
        $target = $this->account(['instructor'], 3);
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $this->signInAccount($this->actor($role))->putJson(route("{$portal}.users.update", $target), $this->payload($target))
                ->assertForbidden();
        }
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_super_admin_designation_conflict_returns_json_validation_error(): void
    {
        $admin = $this->actor('super_admin');
        $this->actor('admin_dean');
        $target = $this->account(['instructor']);

        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), [
            ...$this->payload($target),
            'authorizations' => ['admin_dean'],
        ])->assertUnprocessable()->assertJsonValidationErrors('authorizations');
        $this->assertFalse($target->fresh()->hasRole('admin_dean'));
    }

    public function test_dean_cannot_move_a_chair_to_an_occupied_department(): void
    {
        $dean = $this->actor('admin_dean');
        $target = $this->account(['instructor', 'department_chair']);
        $this->account(['instructor', 'department_chair'], 2);

        $this->signInAccount($dean)->putJson(route('admin-dean.users.update', $target), [
            ...$this->payload($target),
            'department_id' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('authorizations');
        $this->assertSame(1, $target->fresh()->instructorProfile->department_id);
    }

    public function test_html_fallback_keeps_the_users_tab_and_filters_in_the_redirect(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $previousUrl = route('super-admin.users', ['tab' => 'students', 'department' => 1, 'program' => 1]);

        $this->signInAccount($admin)->from($previousUrl)->put(route('super-admin.users.update', $target), $this->payload($target))
            ->assertRedirect($previousUrl)->assertSessionHas('status', 'User account updated successfully.');
    }

    public function test_super_admin_can_switch_a_student_to_teacher_through_ajax(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $payload = $this->payload($target);
        unset($payload['program_id'], $payload['student_number']);

        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), [
            ...$payload,
            'base_role' => 'instructor',
            'department_id' => 2,
            'employee_number' => 'NEW-123',
        ])->assertOk();

        $target->refresh();
        $this->assertTrue($target->hasRole('instructor'));
        $this->assertFalse($target->hasRole('student'));
        $this->assertNull($target->studentProfile);
        $this->assertSame(2, $target->instructorProfile->department_id);
    }

    public function test_ajax_refresh_renders_updated_rows_and_working_edit_forms_with_the_same_filters(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), $this->payload($target))->assertOk();

        $this->get(route('super-admin.users', ['tab' => 'students', 'department' => 1, 'program' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewHas('selectedDepartmentKey', '1')
            ->assertViewHas('selectedProgramKey', '1')
            ->assertSee('Updated Person')
            ->assertSee('value="Updated"', false)
            ->assertSee('js/user-directory.js', false)
            ->assertSee('data-ajax-form data-reload-page-on-success="true" data-preserve-page-state="true"', false);
    }

    public function test_user_search_treats_identifier_wildcards_literally_and_keeps_program_filters(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $target->studentProfile->update(['student_number' => 'STU_100%']);
        $otherProgram = $this->account(['student'], 2);
        $otherProgram->studentProfile->update(['student_number' => 'STU_100%OTHER']);
        $similar = $this->account(['student']);
        $similar->studentProfile->update(['student_number' => 'STUA100X']);

        $this->signInAccount($admin)->get(route('super-admin.users', ['tab' => 'students', 'program' => 1, 'search' => '_100%']))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->modelKeys() === [$target->id])
            ->assertViewHas('programs', fn ($programs) => $programs->count() === 3)
            ->assertSee('data-record-search-form', false)
            ->assertDontSee('data-global-search-url', false);
    }

    public function test_super_admin_user_pages_are_independent_and_only_render_current_page_modals(): void
    {
        $admin = $this->actor('super_admin');
        $teacherIds = [];
        $studentIds = [];
        foreach (range(1, 31) as $number) {
            if ($number <= 30) {
                $teacherIds[] = $this->account(['instructor'])->id;
            }
            $studentIds[] = $this->account(['student'])->id;
        }

        // Identical names still have a consistent order across page boundaries.
        $firstPage = $this->signInAccount($admin)->get(route('super-admin.users'));
        $firstPage->assertOk()
            ->assertViewHas('teachers', fn ($users) => $users->total() === 30 && $users->getCollection()->modelKeys() === array_slice($teacherIds, 0, 25))
            ->assertViewHas('students', fn ($users) => $users->total() === 31 && $users->getCollection()->modelKeys() === array_slice($studentIds, 0, 25))
            ->assertViewHas('users', fn ($users) => $users->count() === 50)
            ->assertSee('class="table-switch-count">30</span>', false)
            ->assertSee('class="table-switch-count">31</span>', false)
            ->assertDontSee('id="viewUserModal'.$teacherIds[25].'"', false)
            ->assertDontSee('id="editUserModal'.$studentIds[25].'"', false);

        $secondPage = $this->get(route('super-admin.users', [
            'tab' => 'students', 'teachers_page' => 2, 'students_page' => 2,
        ]), ['X-Requested-With' => 'XMLHttpRequest']);
        $secondPage->assertOk()
            ->assertViewHas('teachers', fn ($users) => $users->currentPage() === 2 && $users->getCollection()->modelKeys() === array_slice($teacherIds, 25))
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 2 && $users->getCollection()->modelKeys() === array_slice($studentIds, 25))
            ->assertViewHas('users', fn ($users) => $users->count() === 11)
            ->assertSee('Showing 26 to 30 of 30 entries')
            ->assertSee('Showing 26 to 31 of 31 entries')
            ->assertSee('id="viewUserModal'.$teacherIds[25].'"', false)
            ->assertSee('id="editUserModal'.$studentIds[25].'"', false)
            ->assertSee('id="deleteUserModal'.$studentIds[25].'"', false)
            ->assertDontSee('id="viewUserModal'.$teacherIds[0].'"', false);

        parse_str(parse_url($secondPage->viewData('teachers')->url(1), PHP_URL_QUERY), $teacherParams);
        parse_str(parse_url($secondPage->viewData('students')->url(1), PHP_URL_QUERY), $studentParams);
        $this->assertSame(['teachers', '1', '2'], [$teacherParams['tab'], $teacherParams['teachers_page'], $teacherParams['students_page']]);
        $this->assertSame(['students', '2', '1'], [$studentParams['tab'], $studentParams['teachers_page'], $studentParams['students_page']]);

        $this->get(route('super-admin.users', ['teachers_page' => 2]))
            ->assertOk()->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->count() === 25);
    }

    public function test_user_search_and_scope_filters_apply_before_pagination_and_remain_in_links(): void
    {
        $admin = $this->actor('super_admin');
        $matchingIds = [];
        foreach (range(1, 30) as $number) {
            $this->account(['student']);
        }
        foreach (range(1, 27) as $number) {
            $student = $this->account(['student']);
            $student->update(['first_name' => 'Matched']);
            $matchingIds[] = $student->id;
        }
        $this->account(['student'], 3)->update(['first_name' => 'Matched']);
        $this->account(['instructor'], 3)->update(['first_name' => 'Matched']);
        $teacher = $this->account(['instructor']);
        $teacher->update(['first_name' => 'Matched']);

        $response = $this->signInAccount($admin)->get(route('super-admin.users', [
            'tab' => 'students', 'search' => 'mAtChEd', 'department' => 1, 'program' => 1,
            'teachers_page' => 1, 'students_page' => 2,
        ]));
        $response->assertOk()
            ->assertViewHas('students', fn ($users) => $users->total() === 27 && $users->getCollection()->modelKeys() === array_slice($matchingIds, 25))
            ->assertViewHas('teachers', fn ($users) => $users->total() === 1 && $users->first()->id === $teacher->id)
            ->assertSee('Showing 26 to 27 of 27 entries');

        parse_str(parse_url($response->viewData('students')->url(1), PHP_URL_QUERY), $params);
        $this->assertSame('mAtChEd', $params['search']);
        $this->assertSame('1', $params['program']);
        $this->assertSame('1', $params['department']);
        $this->assertSame('students', $params['tab']);

        $this->get(route('super-admin.users', ['search' => 'mAtChEd', 'program' => 1]))
            ->assertOk()->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->count() === 25);
    }

    public function test_user_page_recovers_when_an_edit_removes_the_only_remaining_page_record(): void
    {
        $admin = $this->actor('super_admin');
        foreach (range(1, 25) as $number) {
            $this->account(['student']);
        }
        $target = $this->account(['student']);
        $url = route('super-admin.users', ['tab' => 'students', 'program' => 1, 'students_page' => 2]);

        $this->signInAccount($admin)->get($url)->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 2 && $users->first()->id === $target->id);
        $this->putJson(route('super-admin.users.update', $target), array_merge($this->payload($target), ['program_id' => 2]))
            ->assertOk();
        $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->total() === 25 && $users->count() === 25)
            ->assertSee('Showing 1 to 25 of 25 entries')
            ->assertDontSee('id="viewUserModal'.$target->id.'"', false);

        $this->get(route('super-admin.users', ['tab' => 'students', 'program' => 3, 'students_page' => 999]))
            ->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->total() === 0 && $users->isEmpty())
            ->assertSee('Showing 0 to 0 of 0 entries')
            ->assertDontSee('aria-label="Students pagination"', false);
    }

    public function test_dean_and_chair_user_search_cannot_return_accounts_outside_their_scope(): void
    {
        $target = $this->account(['instructor']);
        $outside = $this->account(['instructor'], 3);
        $this->signInAccount($this->actor('admin_dean'))->get(route('admin-dean.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
        $this->signInAccount($this->actor('department_chair'))->get(route('department-chair.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
    }

    public function test_program_and_subject_search_remain_inside_the_chairs_department(): void
    {
        DB::table('programs')->where('program_id', 1)->update(['public_id' => (string) Str::uuid()]);
        DB::table('subjects')->insert([
            ['subject_id' => 1, 'public_id' => (string) Str::uuid(), 'department_id' => 1, 'subject_code' => 'IT101', 'subject_name' => 'Shared Topic'],
            ['subject_id' => 2, 'public_id' => (string) Str::uuid(), 'department_id' => 3, 'subject_code' => 'IT102', 'subject_name' => 'Shared Topic'],
        ]);
        $this->signInAccount($this->actor('department_chair'))->get(route('department-chair.programs', ['search' => 'program']))
            ->assertOk()->assertViewHas('programs', fn ($programs) => $programs->modelKeys() === [1]);
        $this->get(route('department-chair.subjects', ['search' => 'sHaReD']))
            ->assertOk()->assertViewHas('subjects', fn ($subjects) => $subjects->modelKeys() === [1]);
    }

    public function test_college_acronym_migration_preserves_existing_records_and_department_links(): void
    {
        $migration = require database_path('migrations/2026_10_18_000001_add_college_acronym_to_colleges_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('colleges', 'college_acronym'));
        $colleges = DB::table('colleges')->orderBy('college_id')->get()->toArray();
        $departments = DB::table('departments')->orderBy('department_id')->get()->toArray();
        $migration->up();
        $this->assertEquals($colleges, DB::table('colleges')->select('college_id', 'college_name', 'public_id', 'created_at', 'updated_at')->orderBy('college_id')->get()->toArray());
        $this->assertEquals($departments, DB::table('departments')->orderBy('department_id')->get()->toArray());
        $this->assertSame(2, College::whereNull('college_acronym')->count());
        $this->assertSame('First College', College::findOrFail(1)->shortName());
        $this->assertSame('First College', College::findOrFail(1)->displayName());
    }

    public function test_college_acronym_can_be_saved_normalized_or_left_optional(): void
    {
        foreach ([1, 2, 3] as $departmentId) {
            DB::table('departments')->where('department_id', $departmentId)->update(['public_id' => (string) Str::uuid()]);
        }
        $this->signInAccount($this->actor('super_admin'));
        $this->post(route('super-admin.colleges.store'), [
            'college_name' => 'College of Example Studies', 'college_acronym' => '  ces  ',
        ])->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
        $college = College::where('college_name', 'College of Example Studies')->sole();
        $this->assertSame('CES', $college->college_acronym);
        $this->assertSame('CES', $college->shortName());
        $this->assertSame('College of Example Studies (CES)', $college->displayName());
        foreach (['College Without Acronym', 'Another College Without Acronym'] as $name) {
            $this->post(route('super-admin.colleges.store'), ['college_name' => $name])
                ->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
            $this->assertNull(College::where('college_name', $name)->sole()->college_acronym);
        }
        $this->get(route('super-admin.colleges'))->assertOk()
            ->assertSee('CES')->assertSee('College of Example Studies')
            ->assertSee('name="college_acronym"', false)
            ->assertSee('value="CES"', false);
    }

    public function test_college_acronym_update_keeps_college_identity_and_can_clear_the_optional_value(): void
    {
        $this->signInAccount($this->actor('super_admin'));
        $college = College::findOrFail(1);
        $count = College::count();
        foreach ([
            ['college_name' => 'First College Full Name', 'college_acronym' => 'fc'],
            ['college_name' => 'First College Full Name', 'college_acronym' => 'FC'],
            ['college_name' => 'First College Renamed'],
        ] as $payload) {
            $this->put(route('super-admin.colleges.update', $college), $payload)
                ->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
            $this->assertSame('FC', $college->fresh()->college_acronym);
        }
        $this->assertSame($count, College::count());
        $this->assertSame(2, DB::table('departments')->where('college_id', $college->college_id)->count());
        $this->put(route('super-admin.colleges.update', $college), [
            'college_name' => 'First College Renamed', 'college_acronym' => '',
        ])->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
        $this->assertNull($college->fresh()->college_acronym);
        $this->assertSame('First College Renamed', $college->fresh()->shortName());
    }

    public function test_college_acronym_validation_rejects_duplicates_and_invalid_values_without_saving(): void
    {
        College::findOrFail(1)->update(['college_acronym' => 'FC']);
        $other = College::findOrFail(2);
        $this->signInAccount($this->actor('super_admin'));
        foreach ([' fc ', str_repeat('X', 51), ['not-a-string']] as $value) {
            $this->postJson(route('super-admin.colleges.store'), [
                'college_name' => 'Rejected College', 'college_acronym' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('college_acronym');
            $this->putJson(route('super-admin.colleges.update', $other), [
                'college_name' => 'Rejected Rename', 'college_acronym' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('college_acronym');
        }
        $this->assertSame(2, College::count());
        $this->assertSame('Other College', $other->fresh()->college_name);
        $this->assertNull($other->fresh()->college_acronym);
        // The database constraint also protects writes that bypass HTTP validation.
        $this->expectException(QueryException::class);
        $other->update(['college_acronym' => 'FC']);
    }

    public function test_college_search_matches_acronym_and_full_name_with_or_without_ajax(): void
    {
        foreach ([1, 2, 3] as $departmentId) {
            DB::table('departments')->where('department_id', $departmentId)->update(['public_id' => (string) Str::uuid()]);
        }
        College::findOrFail(1)->update(['college_acronym' => 'FC']);
        $this->signInAccount($this->actor('super_admin'));
        foreach (['fc', 'First College'] as $term) {
            foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
                $this->get(route('super-admin.colleges', ['search' => $term]), $headers)->assertOk()
                    ->assertViewHas('colleges', fn ($colleges) => $colleges->modelKeys() === [1])
                    ->assertViewHas('departments', fn ($departments) => $departments->modelKeys() === [1, 2]);
            }
        }
    }

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

    public function test_search_input_rejects_arrays_and_excessively_long_terms(): void
    {
        $this->signInAccount($this->actor('super_admin'));
        foreach ([['bad'], str_repeat('x', 101)] as $term) {
            $this->getJson(route('super-admin.users', ['search' => $term]))->assertUnprocessable()->assertJsonValidationErrors('search');
        }
    }

    public function test_class_and_nested_assessment_search_preserve_enrollment_and_ownership_scopes(): void
    {
        $student = $this->account(['student']);
        $teacher = $this->account(['instructor']);
        $otherTeacher = $this->account(['instructor'], 3);
        DB::table('subjects')->insert(['subject_id' => 1, 'department_id' => 1, 'subject_code' => 'IT101', 'subject_name' => 'Shared Topic']);
        foreach ([1 => $teacher, 2 => $otherTeacher] as $id => $owner) {
            DB::table('classes')->insert(['class_id' => $id, 'program_id' => 1, 'section_name' => 'Section A', 'school_year' => '2026', 'year_level' => 1]);
            DB::table('class_details')->insert(['class_details_id' => $id, 'class_id' => $id, 'instructor_id' => $owner->instructorProfile->getKey(), 'subject_id' => 1, 'status' => 'approved', 'entry_method' => 'class_setup']);
        }
        DB::table('class_details')->insert(['class_id' => 1, 'instructor_id' => $teacher->instructorProfile->getKey(), 'student_id' => $student->studentProfile->getKey(), 'subject_id' => 1, 'status' => 'approved', 'entry_method' => 'manual_add']);

        $this->signInAccount($student)->get(route('student.classes', ['search' => 'sHaReD']))
            ->assertOk()->assertViewHas('enrolledClasses', fn ($classes) => $classes->modelKeys() === [1]);
        $this->get(route('student.classes', ['search' => 'missing']))
            ->assertOk()->assertViewHas('enrolledClasses', fn ($classes) => $classes->isEmpty());
        $this->assertSame([1], RecordSearch::apply($teacher->instructorProfile->classes()->getQuery(), RecordSearch::CLASS_FIELDS, 'Shared')->get()->modelKeys());

        Schema::create('assessments', function (Blueprint $table) {
            $table->id('assessment_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
        });
        Schema::create('publish_assessment', function (Blueprint $table) {
            $table->id('publish_assessment_id');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_details_id');
        });
        foreach ([1 => $teacher, 2 => $otherTeacher] as $id => $owner) {
            DB::table('assessments')->insert(['assessment_id' => $id, 'instructor_id' => $owner->instructorProfile->getKey(), 'subject_id' => 1, 'title' => 'Quiz']);
            DB::table('publish_assessment')->insert(['publish_assessment_id' => $id, 'assessment_id' => $id, 'class_details_id' => $id]);
        }
        $query = PublishAssessment::whereHas('assessment', fn ($query) => $query->where('instructor_id', $teacher->instructorProfile->getKey()));
        foreach (['Shared', 'Section A', 'Quiz'] as $term) {
            $this->assertSame([1], RecordSearch::apply(clone $query, RecordSearch::PUBLISHED_FIELDS, $term)->get()->modelKeys());
        }
        request()->query->set('search', 'Shared');
        $this->assertSame([1], RecordSearch::filter($query->get(), RecordSearch::PUBLISHED_FIELDS)->modelKeys());
    }

    public static function studentImportPortals(): array
    {
        return [
            'super admin' => ['super_admin', 'super-admin.users.students.import'],
            'department chair' => ['department_chair', 'department-chair.students.import'],
        ];
    }

    #[DataProvider('studentImportPortals')]
    public function test_student_import_logs_each_created_account_with_the_actual_importer(string $actorRole, string $routePrefix): void
    {
        Mail::fake();
        $actor = $this->actor($actorRole);
        $existing = $this->account(['student']);
        $this->signInAccount($actor);
        $csv = "student_number,first_name,last_name,email\n"
            ."2026-90001,Juan,Dela Cruz,juan.import@example.test\n"
            ."2026-90002,Maria,Santos,maria.import@example.test\n"
            ."{$existing->studentProfile->student_number},Existing,Student,{$existing->email}\n"
            ."2026-90003,Invalid,Student,not-an-email\n";
        $token = $this->previewStudentImport($routePrefix, $csv);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', '2 student accounts created successfully.');

        $this->assertDatabaseCount('audit_logs', 2);
        foreach (['juan.import@example.test', 'maria.import@example.test'] as $email) {
            $created = User::where('email', $email)->firstOrFail();
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $actor->id, 'user_role' => $actorRole, 'action' => 'CREATE', 'module' => 'Users',
                'auditable_type' => User::class, 'auditable_id' => $created->id,
                'description' => "Created student account for {$created->displayName()} ({$email}) via CSV import",
            ]);
            $this->assertSame(1, $created->studentProfile->program_id);
            $this->assertTrue($created->hasRole('student'));
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'IMPORT']);
        $this->assertDatabaseMissing('audit_logs', ['auditable_id' => $existing->id, 'auditable_type' => User::class]);
        Mail::assertSent(\App\Mail\InitialAccountPasswordMail::class, 2);

        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseCount('audit_logs', 2);
        $viewer = $actorRole === 'super_admin' ? $actor : $this->actor('super_admin');
        $this->signInAccount($viewer)->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'CREATE']))
            ->assertOk()->assertSee('juan.import@example.test')->assertSee('maria.import@example.test')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
    }

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

    private function previewStudentImport(string $routePrefix, string $csv): string
    {
        $preview = $this->post(route("{$routePrefix}.preview"), [
            'import_program_id' => 1,
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($preview->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertTrue(Str::isUuid($query['import_token'] ?? ''));
        return $query['import_token'];
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
        Mail::assertSent(\App\Mail\InitialAccountPasswordMail::class);
    }

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

    public function test_optional_audit_failure_is_reported_without_throwing(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        DB::statement("CREATE TRIGGER reject_optional_audit BEFORE INSERT ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'Simulated optional audit failure'); END");
        AuditLogger::log('LOGIN', 'Auth', 'User logged in successfully.');
        $this->assertDatabaseCount('audit_logs', 0);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with(
            'Audit log could not be recorded.',
            \Mockery::on(fn ($context) => $context['action'] === 'LOGIN'
                && $context['module'] === 'Auth' && $context['exception_type'] === QueryException::class)
        );
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
                if ($portal === 'department-chair' && $baseRole === 'instructor') continue;
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
        Mail::assertSent(\App\Mail\InitialAccountPasswordMail::class, 1);
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
        Mail::assertSent(\App\Mail\InitialAccountPasswordMail::class, 2);
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

    #[DataProvider('accountUpdates')]
    public function test_live_availability_excludes_only_the_authorized_edited_account(string $portal, string $actorRole, string $baseRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account([$baseRole]);
        $other = $this->account([$baseRole]);
        $this->signInAccount($actor);
        $field = $baseRole === 'instructor' ? 'employee_number' : 'student_number';
        $profile = $baseRole === 'instructor' ? 'instructorProfile' : 'studentProfile';
        foreach (['email' => $target->email, $field => $target->$profile->$field] as $name => $value) {
            $this->postJson(route("{$portal}.users.availability.edit", $target), ['field' => $name, 'value' => $value])
                ->assertOk()->assertJsonPath('available', true);
        }
        foreach (['email' => $other->email, $field => $other->$profile->$field] as $name => $value) {
            $this->postJson(route("{$portal}.users.availability.edit", $target), [
                'field' => $name, 'value' => $value, 'user_id' => $other->id, 'ignore' => $other->id,
            ])->assertOk()->assertJsonPath('available', false);
        }
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_live_availability_does_not_allow_excluding_accounts_outside_the_editors_scope(): void
    {
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $this->signInAccount($this->actor($role));
            foreach (['instructor', 'student'] as $baseRole) {
                $outside = $this->account([$baseRole], 3);
                $this->postJson(route("{$portal}.users.availability.edit", $outside), [
                    'field' => 'email', 'value' => $outside->email,
                ])->assertForbidden();
            }
        }
        $chair = $this->actor('department_chair');
        $sameCollegeOtherDepartment = $this->account(['student'], 2);
        $this->signInAccount($chair)->postJson(route('department-chair.users.availability.edit', $sameCollegeOtherDepartment), [
            'field' => 'email', 'value' => $sameCollegeOtherDepartment->email,
        ])->assertForbidden();
    }

    public function test_live_availability_requires_valid_input_and_an_authorized_active_account(): void
    {
        $url = route('super-admin.users.availability');
        $this->postJson($url, ['field' => 'email', 'value' => 'test@example.test'])->assertUnauthorized();
        $admin = $this->actor('super_admin');
        $this->signInAccount($admin);
        foreach ([
            ['field' => 'password', 'value' => 'secret'],
            ['field' => ['email'], 'value' => 'test@example.test'],
            ['field' => 'email', 'value' => 'not-an-email'],
            ['field' => 'student_number', 'value' => ['STU-123']],
            ['field' => 'student_number', 'value' => str_repeat('x', 256)],
            ['field' => 'email', 'value' => ''],
        ] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
        }
        $this->postJson(route('super-admin.users.availability.edit', $admin), [
            'field' => 'email', 'value' => $admin->email,
        ])->assertForbidden();
        $this->signInAccount($this->account(['student']))->postJson($url, [
            'field' => 'email', 'value' => 'test@example.test',
        ])->assertForbidden();
        $admin->update(['status' => 'inactive']);
        $this->signInAccount($admin)->postJson($url, ['field' => 'email', 'value' => 'test@example.test'])
            ->assertForbidden();
    }

    public function test_live_availability_requires_an_assigned_academic_scope_and_limits_requests(): void
    {
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $actor = $this->actor($role);
            $actor->instructorProfile->delete();
            $actor->refresh(); // Real requests reload the actor instead of retaining the deleted relation.
            $this->signInAccount($actor)->postJson(route("{$portal}.users.availability"), [
                'field' => 'email', 'value' => 'test@example.test',
            ])->assertForbidden();
        }
        $this->signInAccount($this->actor('super_admin'));
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('super-admin.users.availability'), ['field' => 'email', 'value' => 'unused@example.test'])
                ->assertOk();
        }
        $this->postJson(route('super-admin.users.availability'), ['field' => 'email', 'value' => 'unused@example.test'])
            ->assertTooManyRequests();
        // The availability limit must not consume the separate save endpoint's allowance.
        $this->putJson(route('super-admin.users.update', $this->account(['student'])), ['email' => 'invalid'])
            ->assertUnprocessable();
    }

    public static function availabilityFormPages(): array
    {
        // The Dean currently has a Teachers page; there is no student directory in that portal.
        return array_filter(self::accountUpdates(), fn (array $case) => $case[0] !== 'admin-dean' || $case[2] !== 'student');
    }

    #[DataProvider('availabilityFormPages')]
    public function test_live_availability_urls_are_present_in_ajax_loaded_create_and_edit_forms(string $portal, string $actorRole, string $baseRole): void
    {
        $target = $this->account([$baseRole]);
        $page = $portal === 'super-admin' ? 'users'
            : ($portal === 'department-chair' && $baseRole === 'student' ? 'students' : 'teachers');
        $response = $this->signInAccount($this->actor($actorRole))->get(route("{$portal}.{$page}"), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();
        foreach ([
            'js/user-availability.js',
            'data-user-availability-url="'.route("{$portal}.users.availability").'"',
            'data-user-availability-url="'.route("{$portal}.users.availability.edit", $target).'"',
        ] as $expected) {
            $this->assertTrue(str_contains($response->getContent(), $expected), "Missing availability wiring on {$portal} {$baseRole} form: {$expected}");
        }
    }

    private function creationPayload(string $baseRole): array
    {
        return [
            'first_name' => 'New', 'last_name' => 'Account', 'email' => 'new-account@example.test',
            'status' => 'active', 'base_role' => $baseRole,
            ...($baseRole === 'instructor'
                ? ['department_id' => 1, 'employee_number' => 'EMP-987']
                : ['program_id' => 1, 'student_number' => 'STU-987']),
        ];
    }

    private function enrollmentMigration(): \Illuminate\Database\Migrations\Migration
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

    private function enrollmentActorMigration(): \Illuminate\Database\Migrations\Migration
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

    private function signInAccount(User $user): static
    {
        // Account switching in CRUD fixtures must emulate the markers set by a real login.
        return $this->withSession([
            AccountSessionService::VERSION_KEY => (int) $user->session_version,
            'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->getAuthPassword()),
        ])->actingAs($user);
    }

    private function actor(string $role): User
    {
        return $this->account($role === 'super_admin' ? [$role] : ['instructor', $role]);
    }

    private function account(array $roles, int $departmentId = 1): User
    {
        $number = User::count() + 1;
        $user = User::create([
            'name' => 'Original Person', 'first_name' => 'Original', 'last_name' => 'Person',
            'email' => "person{$number}@example.test", 'password' => 'test-password',
            'status' => 'active', 'must_change_password' => false,
        ]);
        $user->roles()->sync(Role::whereIn('role_name', $roles)->pluck('role_id'));
        if (in_array('instructor', $roles, true)) {
            InstructorProfile::create(['user_id' => $user->id, 'department_id' => $departmentId, 'employee_number' => "EMP-{$number}"]);
        }
        if (in_array('student', $roles, true)) {
            StudentProfile::create(['user_id' => $user->id, 'program_id' => $departmentId, 'student_number' => "STU-{$number}"]);
        }
        return $user;
    }

    private function payload(User $user): array
    {
        $payload = ['first_name' => 'Updated', 'last_name' => 'Person', 'email' => $user->email, 'status' => 'active'];
        if ($user->hasRole('instructor')) {
            return [...$payload, 'base_role' => 'instructor', 'department_id' => $user->instructorProfile->department_id, 'employee_number' => $user->instructorProfile->employee_number];
        }
        return [...$payload, 'base_role' => 'student', 'program_id' => $user->studentProfile->program_id, 'student_number' => $user->studentProfile->student_number];
    }
}
