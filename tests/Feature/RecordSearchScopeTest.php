<?php

namespace Tests\Feature;

use App\Mail\InitialAccountPasswordMail;
use App\Models\PublishAssessment;
use App\Models\User;
use App\Support\RecordSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class RecordSearchScopeTest extends AccountFeatureTestCase
{
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
        Mail::assertSent(InitialAccountPasswordMail::class, 2);

        $this->post(route("{$routePrefix}.confirm"), ['import_token' => $token])->assertSessionHasErrorsIn('studentImport');
        $this->assertDatabaseCount('audit_logs', 2);
        $viewer = $actorRole === 'super_admin' ? $actor : $this->actor('super_admin');
        $this->signInAccount($viewer)->get(route('super-admin.audit-logs', ['module' => 'Users', 'action' => 'CREATE']))
            ->assertOk()->assertSee('juan.import@example.test')->assertSee('maria.import@example.test')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
    }
}
