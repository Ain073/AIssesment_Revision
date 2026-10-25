<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\PublishAssessment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\AccountFeatureTestCase;

class AssessmentPublishDueDateTest extends AccountFeatureTestCase
{
    private Assessment $assessment;

    private AcademicClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('classes', fn (Blueprint $table) => $table->uuid('public_id')->nullable());
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id('assessment_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->string('report_category')->nullable();
            $table->string('reporting_term')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('assessment_items', function (Blueprint $table): void {
            $table->id('assessment_item_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('assessment_id');
            $table->text('question_text');
            $table->string('item_type');
            $table->decimal('points');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
        Schema::create('assessment_item_choices', function (Blueprint $table): void {
            $table->id('assessment_item_choice_id');
            $table->unsignedBigInteger('assessment_item_id');
            $table->text('choice_text');
            $table->boolean('is_correct');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });
        Schema::create('publish_assessment', function (Blueprint $table): void {
            $table->id('publish_assessment_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('class_details_id');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('publish_status');
            $table->boolean('score_visibility')->default(false);
            $table->boolean('answer_visibility')->default(false);
            $table->boolean('prevent_copy_paste')->default(false);
            $table->boolean('detect_tab_switch')->default(false);
            $table->boolean('screenshot_protection')->default(false);
            $table->unsignedInteger('attempt_limit')->default(1);
            $table->boolean('shuffle_items')->default(false);
            $table->boolean('shuffle_choices')->default(false);
            $table->unsignedInteger('warning_limit')->default(3);
            $table->string('display_mode');
            $table->unsignedInteger('question_time_limit_seconds')->nullable();
            $table->timestamps();
        });

        $teacher = $this->account(['instructor']);
        $student = $this->account(['student']);
        $subjectId = DB::table('subjects')->insertGetId([
            'department_id' => 1, 'subject_code' => 'DUE-101', 'subject_name' => 'Due date test', 'is_active' => true,
            'public_id' => (string) Str::uuid(),
        ]);
        $classId = DB::table('classes')->insertGetId([
            'public_id' => (string) Str::uuid(), 'program_id' => 1, 'year_level' => 1, 'section_name' => 'A',
        ]);
        $this->class = AcademicClass::findOrFail($classId);
        $this->class->syncContext($teacher->instructorProfile, $subjectId);
        $this->class->enrollStudent($student->studentProfile);
        $this->assessment = Assessment::create([
            'instructor_id' => $teacher->instructorProfile->getKey(), 'subject_id' => $subjectId,
            'title' => 'Sample assessment', 'status' => Assessment::STATUS_READY,
        ]);
        $this->assessment->items()->create([
            'question_text' => 'Question', 'item_type' => 'identification', 'points' => 1,
        ]);
        $this->signInAccount($teacher);
    }

    public function test_both_publish_routes_reject_missing_due_date_and_valid_date_publishes(): void
    {
        $this->get(route('instructor.assessments.publish.form'))->assertOk()
            ->assertSee('Due At (Required)')
            ->assertSee('name="due_at" type="datetime-local" value="" required', false);

        $payload = [
            'subject_id' => $this->assessment->subject_id,
            'assessment_key' => $this->assessment->public_id,
            'class_keys' => [$this->class->public_id],
            'available_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'attempt_limit' => 1,
            'display_mode' => PublishAssessment::DISPLAY_ALL_QUESTIONS,
        ];

        $this->post(route('instructor.assessments.publish.selected'), $payload)
            ->assertSessionHasErrors('due_at');
        $this->post(route('instructor.assessments.publish', $this->assessment), $payload)
            ->assertSessionHasErrors('due_at');
        $this->post(route('instructor.assessments.publish.selected'), $payload + ['due_at' => ''])
            ->assertSessionHasErrors('due_at');
        $this->assertDatabaseCount('publish_assessment', 0);

        $dueAt = now()->addDay()->format('Y-m-d H:i:s');
        $this->post(route('instructor.assessments.publish.selected'), $payload + ['due_at' => $dueAt])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($dueAt, PublishAssessment::query()->sole()->due_at->format('Y-m-d H:i:s'));
    }
}
