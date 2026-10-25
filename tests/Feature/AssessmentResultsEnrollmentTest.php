<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountFeatureTestCase;

class AssessmentResultsEnrollmentTest extends AccountFeatureTestCase
{
    private AcademicClass $class;

    private PublishAssessment $published;

    private AssessmentItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $tables = [
            'assessments' => 'assessment_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, instructor_id INTEGER, subject_id INTEGER, title TEXT, status TEXT, created_at DATETIME, updated_at DATETIME',
            'assessment_items' => 'assessment_item_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, question_text TEXT, item_type TEXT, points DECIMAL, sort_order INTEGER, created_at DATETIME, updated_at DATETIME',
            'assessment_item_choices' => 'assessment_item_choice_id INTEGER PRIMARY KEY, assessment_item_id INTEGER, choice_text TEXT, is_correct INTEGER, sort_order INTEGER, created_at DATETIME, updated_at DATETIME',
            'publish_assessment' => 'publish_assessment_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, class_details_id INTEGER, due_at DATETIME, original_due_at DATETIME, publish_status TEXT, created_at DATETIME, updated_at DATETIME',
            'submissions' => 'submission_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, publish_assessment_id INTEGER, student_profile_id INTEGER, attempt_number INTEGER, status TEXT, completion_reason TEXT, warning_count INTEGER DEFAULT 0, submitted_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'submission_answers' => 'submission_answer_id INTEGER PRIMARY KEY, submission_id INTEGER, assessment_item_id INTEGER, assessment_item_choice_id INTEGER, answer_text TEXT, earned_points DECIMAL, created_at DATETIME, updated_at DATETIME',
            'submission_security_events' => 'submission_security_event_id INTEGER PRIMARY KEY, submission_id INTEGER, event_type TEXT, occurred_at DATETIME',
            'passing_rate_settings' => 'id INTEGER PRIMARY KEY, year_level INTEGER, passing_rate FLOAT',
        ];
        foreach ($tables as $name => $columns) {
            DB::statement("CREATE TABLE {$name} ({$columns})");
        }
        PassingRateSetting::clearRateCache();

        $teacher = $this->account(['instructor']);
        $subjectId = DB::table('subjects')->insertGetId([
            'department_id' => 1, 'subject_code' => 'IC1', 'subject_name' => 'Sample course', 'is_active' => true,
        ]);
        $classId = DB::table('classes')->insertGetId(['program_id' => 1, 'year_level' => 2, 'section_name' => 'A']);
        $this->class = AcademicClass::findOrFail($classId);
        $context = $this->class->syncContext($teacher->instructorProfile, $subjectId);
        $assessment = Assessment::create([
            'instructor_id' => $teacher->instructorProfile->getKey(), 'subject_id' => $subjectId,
            'title' => 'Quiz 1', 'status' => Assessment::STATUS_ARCHIVED,
        ]);
        $this->item = $assessment->items()->create([
            'question_text' => 'Identify the answer.', 'item_type' => 'identification', 'points' => 1, 'sort_order' => 1,
        ]);
        $this->item->choices()->create(['choice_text' => 'Correct answer', 'is_correct' => true, 'sort_order' => 1]);
        $this->published = PublishAssessment::create([
            'assessment_id' => $assessment->getKey(), 'class_details_id' => $context->getKey(),
            'publish_status' => PublishAssessment::STATUS_CLOSED, 'due_at' => now()->subDay(),
        ]);
        $this->signInAccount($teacher);
    }

    protected function tearDown(): void
    {
        PassingRateSetting::clearRateCache();
        parent::tearDown();
    }

    public function test_31_submitters_and_30_current_students_have_separate_counts_and_preserved_results(): void
    {
        for ($index = 0; $index < 31; $index++) {
            $student = $this->account(['student']);
            $this->class->enrollStudent($student->studentProfile);
            $this->attempt($student);
        }
        $this->class->removeStudent($student->studentProfile);
        $before = $this->submissionRecords();

        $response = $this->get($this->url())->assertOk()
            ->assertViewHas('submittedStudentsCount', 31)
            ->assertViewHas('currentEnrolledCount', 30)
            ->assertSeeInOrder(['Students Submitted', '>31<', 'Currently Enrolled', '>30<'], false)
            ->assertDontSee('31 / 30')
            ->assertSee('No longer enrolled');
        $rows = $response->viewData('studentResults')->keyBy(fn ($row) => $row['student']->getKey());
        $formerStudent = $rows->get($student->studentProfile->getKey());
        $this->assertFalse($formerStudent['is_currently_enrolled']);
        $this->assertSame(1.0, $formerStudent['best_attempt']['score_value']);
        $this->assertSame(30, $rows->where('is_currently_enrolled', true)->count());
        $this->assertCount(31, $rows);
        $this->assertSame($before, $this->submissionRecords());
    }

    public function test_reenrollment_removes_the_former_student_label_without_another_submission(): void
    {
        $student = $this->account(['student']);
        $this->class->enrollStudent($student->studentProfile);
        $this->attempt($student);
        $this->class->removeStudent($student->studentProfile);
        $before = $this->submissionRecords();
        $this->get($this->url())->assertOk()->assertViewHas('currentEnrolledCount', 0)
            ->assertViewHas('submittedStudentsCount', 1)->assertSee('No longer enrolled');

        $this->class->enrollStudent($student->studentProfile);
        $this->get($this->url())->assertOk()->assertViewHas('currentEnrolledCount', 1)
            ->assertViewHas('submittedStudentsCount', 1)->assertDontSee('No longer enrolled')
            ->assertViewHas('studentResults', fn ($rows) => $rows->first()['is_currently_enrolled'] === true);
        $this->assertSame($before, $this->submissionRecords());
    }

    public function test_submitted_student_count_deduplicates_attempts_and_includes_auto_submissions(): void
    {
        $manual = $this->account(['student']);
        $auto = $this->account(['student']);
        $pending = $this->account(['student']);
        foreach ([$manual, $auto, $pending] as $student) {
            $this->class->enrollStudent($student->studentProfile);
        }
        $this->attempt($manual);
        $this->attempt($manual, attempt: 2, reason: Submission::COMPLETION_WARNING_LIMIT);
        $this->attempt($auto, reason: Submission::COMPLETION_WARNING_LIMIT);
        $this->attempt($auto, attempt: 2, reason: Submission::COMPLETION_WARNING_LIMIT);
        $this->attempt($pending, status: Submission::STATUS_IN_PROGRESS);

        $this->get($this->url())->assertOk()->assertViewHas('submittedStudentsCount', 2)
            ->assertViewHas('currentEnrolledCount', 3)->assertViewHas('autoSubmittedCount', 3)
            ->assertViewHas('analytics', fn ($analytics) => $analytics['takers_count'] === 1)
            ->assertDontSee('No longer enrolled')->assertSee('Auto-submitted Attempts');
    }

    public function test_empty_results_and_live_submissions_use_the_same_separate_counts(): void
    {
        $this->get($this->url())->assertOk()->assertViewHas('submittedStudentsCount', 0)
            ->assertViewHas('currentEnrolledCount', 0)->assertSee('No students in this class');

        $this->published->update(['publish_status' => PublishAssessment::STATUS_PUBLISHED, 'due_at' => now()->addDay()]);
        $student = $this->account(['student']);
        $this->class->enrollStudent($student->studentProfile);
        $this->attempt($student, status: Submission::STATUS_IN_PROGRESS);
        $this->get($this->url())->assertOk()->assertViewHas('isCompleted', false)
            ->assertViewHas('submittedStudentsCount', 0)->assertViewHas('currentEnrolledCount', 1);
    }

    public function test_counts_and_historical_results_are_still_private_to_the_owner(): void
    {
        $this->attempt($this->account(['student']));
        $this->signInAccount($this->account(['instructor']));
        $this->get($this->url())->assertForbidden();
    }

    private function url(): string
    {
        return route('instructor.assessments.results', $this->published);
    }

    private function attempt(User $student, int $attempt = 1, string $status = Submission::STATUS_SUBMITTED, string $reason = Submission::COMPLETION_MANUAL): void
    {
        $submission = Submission::create([
            'publish_assessment_id' => $this->published->getKey(), 'student_profile_id' => $student->studentProfile->getKey(),
            'attempt_number' => $attempt, 'status' => $status, 'completion_reason' => $reason,
            'submitted_at' => $status === Submission::STATUS_SUBMITTED ? now() : null,
        ]);
        $submission->answers()->create(['assessment_item_id' => $this->item->getKey(), 'answer_text' => 'Correct answer']);
    }

    private function submissionRecords(): array
    {
        return [
            DB::table('submissions')->orderBy('submission_id')->get()->toJson(),
            DB::table('submission_answers')->orderBy('submission_answer_id')->get()->toJson(),
        ];
    }
}
