<?php

namespace Tests\Feature;

use App\Models\InstructorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
