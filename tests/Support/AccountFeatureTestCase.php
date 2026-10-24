<?php

namespace Tests\Support;

use App\Models\InstructorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AccountFeatureTestCase extends TestCase
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
            $table->uuid('public_id')->nullable()->unique();
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

    public static function studentImportPortals(): array
    {
        return [
            'super admin' => ['super_admin', 'super-admin.users.students.import'],
            'department chair' => ['department_chair', 'department-chair.students.import'],
        ];
    }

    protected function previewStudentImport(string $routePrefix, string $csv): string
    {
        $preview = $this->post(route("{$routePrefix}.preview"), [
            'import_program_id' => 1,
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($preview->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertTrue(Str::isUuid($query['import_token'] ?? ''));

        return $query['import_token'];
    }

    protected function creationPayload(string $baseRole): array
    {
        return [
            'first_name' => 'New', 'last_name' => 'Account', 'email' => 'new-account@example.test',
            'status' => 'active', 'base_role' => $baseRole,
            ...($baseRole === 'instructor'
                ? ['department_id' => 1, 'employee_number' => 'EMP-987']
                : ['program_id' => 1, 'student_number' => 'STU-987']),
        ];
    }

    protected function signInAccount(User $user): static
    {
        // Account switching in CRUD fixtures must emulate the markers set by a real login.
        return $this->withSession([
            AccountSessionService::VERSION_KEY => (int) $user->session_version,
            'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->getAuthPassword()),
        ])->actingAs($user);
    }

    protected function actor(string $role): User
    {
        return $this->account($role === 'super_admin' ? [$role] : ['instructor', $role]);
    }

    protected function account(array $roles, int $departmentId = 1): User
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

    protected function payload(User $user): array
    {
        $payload = ['first_name' => 'Updated', 'last_name' => 'Person', 'email' => $user->email, 'status' => 'active'];
        if ($user->hasRole('instructor')) {
            return [...$payload, 'base_role' => 'instructor', 'department_id' => $user->instructorProfile->department_id, 'employee_number' => $user->instructorProfile->employee_number];
        }

        return [...$payload, 'base_role' => 'student', 'program_id' => $user->studentProfile->program_id, 'student_number' => $user->studentProfile->student_number];
    }
}
