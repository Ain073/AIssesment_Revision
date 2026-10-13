<?php

namespace Tests\Feature;

use App\Models\InstructorProfile;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\PublishAssessment;
use App\Support\RecordSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        });
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
        });
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at')->nullable();
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
            ['college_id' => 1, 'college_name' => 'First College'],
            ['college_id' => 2, 'college_name' => 'Other College'],
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

        $response = $this->actingAs($actor)->get($page, ['X-Requested-With' => 'XMLHttpRequest'])
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
    }

    #[DataProvider('designationTypes')]
    public function test_designation_picker_remains_available_without_eligible_teachers(string $type): void
    {
        $this->createDesignationTables();
        $isDean = $type === 'dean';
        $this->actingAs($this->actor($isDean ? 'super_admin' : 'admin_dean'))
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

        $this->actingAs($actor)->post(route("{$portal}.users.update", $target), [
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
            $this->assertDatabaseHas('audit_logs', ['action' => 'UPDATE', 'module' => 'Users', 'auditable_id' => $target->id]);
        }
    }

    #[DataProvider('portals')]
    public function test_invalid_ajax_update_returns_422_without_saving(string $portal, string $actorRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account(['instructor']);
        $payload = $this->payload($target);
        $payload['email'] = $actor->email;

        $this->actingAs($actor)->putJson(route("{$portal}.users.update", $target), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_dean_and_chair_cannot_update_users_outside_their_scope(): void
    {
        $target = $this->account(['instructor'], 3);
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $this->actingAs($this->actor($role))->putJson(route("{$portal}.users.update", $target), $this->payload($target))
                ->assertForbidden();
        }
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_super_admin_designation_conflict_returns_json_validation_error(): void
    {
        $admin = $this->actor('super_admin');
        $this->actor('admin_dean');
        $target = $this->account(['instructor']);

        $this->actingAs($admin)->putJson(route('super-admin.users.update', $target), [
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

        $this->actingAs($dean)->putJson(route('admin-dean.users.update', $target), [
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

        $this->actingAs($admin)->from($previousUrl)->put(route('super-admin.users.update', $target), $this->payload($target))
            ->assertRedirect($previousUrl)->assertSessionHas('status', 'User account updated successfully.');
    }

    public function test_super_admin_can_switch_a_student_to_teacher_through_ajax(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $payload = $this->payload($target);
        unset($payload['program_id'], $payload['student_number']);

        $this->actingAs($admin)->putJson(route('super-admin.users.update', $target), [
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
        $this->actingAs($admin)->putJson(route('super-admin.users.update', $target), $this->payload($target))->assertOk();

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

        $this->actingAs($admin)->get(route('super-admin.users', ['tab' => 'students', 'program' => 1, 'search' => '_100%']))
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
        $firstPage = $this->actingAs($admin)->get(route('super-admin.users'));
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

        $response = $this->actingAs($admin)->get(route('super-admin.users', [
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

        $this->actingAs($admin)->get($url)->assertOk()
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
        $this->actingAs($this->actor('admin_dean'))->get(route('admin-dean.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
        $this->actingAs($this->actor('department_chair'))->get(route('department-chair.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
    }

    public function test_program_and_subject_search_remain_inside_the_chairs_department(): void
    {
        DB::table('programs')->where('program_id', 1)->update(['public_id' => (string) Str::uuid()]);
        DB::table('subjects')->insert([
            ['subject_id' => 1, 'public_id' => (string) Str::uuid(), 'department_id' => 1, 'subject_code' => 'IT101', 'subject_name' => 'Shared Topic'],
            ['subject_id' => 2, 'public_id' => (string) Str::uuid(), 'department_id' => 3, 'subject_code' => 'IT102', 'subject_name' => 'Shared Topic'],
        ]);
        $this->actingAs($this->actor('department_chair'))->get(route('department-chair.programs', ['search' => 'program']))
            ->assertOk()->assertViewHas('programs', fn ($programs) => $programs->modelKeys() === [1]);
        $this->get(route('department-chair.subjects', ['search' => 'sHaReD']))
            ->assertOk()->assertViewHas('subjects', fn ($subjects) => $subjects->modelKeys() === [1]);
    }

    public function test_audit_search_finds_matches_before_pagination_and_keeps_other_filters(): void
    {
        $admin = $this->actor('super_admin');
        foreach (range(1, 30) as $number) {
            AuditLog::create(['user_id' => $admin->id, 'action' => 'CREATE', 'module' => 'Programs', 'description' => "Noise {$number}", 'created_at' => now()]);
        }
        AuditLog::create(['user_id' => $admin->id, 'action' => 'CREATE', 'module' => 'Programs', 'description' => 'Needle record', 'created_at' => now()->subDay()]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'DELETE', 'module' => 'Programs', 'description' => 'Needle record', 'created_at' => now()]);

        $this->actingAs($admin)->get(route('super-admin.audit-logs', ['search' => 'needle', 'action' => 'CREATE']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->description === 'Needle record')
            ->assertSee('data-record-search-input', false);
    }

    public function test_search_input_rejects_arrays_and_excessively_long_terms(): void
    {
        $this->actingAs($this->actor('super_admin'));
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

        $this->actingAs($student)->get(route('student.classes', ['search' => 'sHaReD']))
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
