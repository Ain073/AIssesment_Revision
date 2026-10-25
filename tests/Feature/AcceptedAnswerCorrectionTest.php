<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\AuditLog;
use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\User;
use App\Support\AssessmentScoring;
use App\Support\ReportAnalytics;
use App\Support\ReportItemAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\AccountFeatureTestCase;

class AcceptedAnswerCorrectionTest extends AccountFeatureTestCase
{
    private User $teacher;

    private Assessment $assessment;

    private AssessmentItem $item;

    private PublishAssessment $published;

    protected function setUp(): void
    {
        parent::setUp();

        $tables = [
            'assessments' => 'assessment_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, instructor_id INTEGER, subject_id INTEGER, title TEXT, description TEXT, type TEXT, report_category TEXT, reporting_term TEXT, instructions TEXT, status TEXT, created_at DATETIME, updated_at DATETIME',
            'assessment_items' => 'assessment_item_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, question_text TEXT, item_type TEXT, points DECIMAL, is_required INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 1, order_sensitive INTEGER DEFAULT 0, created_at DATETIME, updated_at DATETIME',
            'assessment_item_choices' => 'assessment_item_choice_id INTEGER PRIMARY KEY, assessment_item_id INTEGER, choice_text TEXT, is_correct INTEGER, sort_order INTEGER, created_at DATETIME, updated_at DATETIME',
            'publish_assessment' => 'publish_assessment_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, assessment_id INTEGER, class_details_id INTEGER, available_at DATETIME, due_at DATETIME, original_due_at DATETIME, publish_status TEXT, score_visibility INTEGER DEFAULT 1, answer_visibility INTEGER DEFAULT 1, attempt_limit INTEGER DEFAULT 3, created_at DATETIME, updated_at DATETIME',
            'submissions' => 'submission_id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, publish_assessment_id INTEGER, student_profile_id INTEGER, attempt_number INTEGER, status TEXT, completion_reason TEXT, warning_count INTEGER DEFAULT 0, started_at DATETIME, last_activity_at DATETIME, submitted_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'submission_answers' => 'submission_answer_id INTEGER PRIMARY KEY, submission_id INTEGER, assessment_item_id INTEGER, assessment_item_choice_id INTEGER, answer_text TEXT, earned_points DECIMAL, feedback TEXT, checked_by INTEGER, checked_at DATETIME, created_at DATETIME, updated_at DATETIME',
            'submission_security_events' => 'submission_security_event_id INTEGER PRIMARY KEY, submission_id INTEGER, event_type TEXT, occurred_at DATETIME',
            'passing_rate_settings' => 'id INTEGER PRIMARY KEY, year_level INTEGER, passing_rate FLOAT',
        ];
        foreach ($tables as $name => $columns) {
            DB::statement("CREATE TABLE {$name} ({$columns})");
        }
        PassingRateSetting::clearRateCache();

        $this->teacher = $this->account(['instructor']);
        $subjectId = DB::table('subjects')->insertGetId([
            'department_id' => 1, 'subject_code' => 'PSY-101', 'subject_name' => 'Psychology', 'is_active' => true,
        ]);
        $classId = DB::table('classes')->insertGetId(['program_id' => 1, 'year_level' => 1, 'section_name' => 'A']);
        $class = AcademicClass::findOrFail($classId);
        $class->syncContext($this->teacher->instructorProfile, $subjectId);
        $this->assessment = Assessment::create([
            'instructor_id' => $this->teacher->instructorProfile->getKey(), 'subject_id' => $subjectId,
            'title' => 'Published identification quiz', 'status' => Assessment::STATUS_ARCHIVED,
            'report_category' => 'formative',
        ]);
        $this->item = $this->assessment->items()->create([
            'question_text' => 'They believe in self-improvement.', 'item_type' => 'identification', 'points' => 1, 'sort_order' => 30,
        ]);
        $this->item->choices()->create(['choice_text' => 'Humanistic psychologists', 'is_correct' => true, 'sort_order' => 1]);
        $this->published = PublishAssessment::create([
            'assessment_id' => $this->assessment->getKey(), 'class_details_id' => $class->publishContextClassDetail()->getKey(),
            'publish_status' => PublishAssessment::STATUS_CLOSED, 'due_at' => now()->subDay(),
        ]);
        $this->signInAccount($this->teacher);
    }

    protected function tearDown(): void
    {
        PassingRateSetting::clearRateCache();
        parent::tearDown();
    }

    public function test_preview_is_read_only_and_save_rechecks_all_matching_attempts_without_rewriting_answers(): void
    {
        $student = $this->account(['student']);
        $first = $this->answer('Humanistic psychologist', $student);
        $second = $this->answer('  HUMANISTIC PSYCHOLOGIST  ', $student, 2);
        $third = $this->answer('Humanistic psychologist');
        $correct = $this->answer('Humanistic psychologists');
        $wrong = $this->answer('Behaviorist');
        $pending = $this->answer('Humanistic psychologist', status: Submission::STATUS_IN_PROGRESS);
        $before = DB::table('submission_answers')->orderBy('submission_answer_id')->get()->toJson();
        $questionBefore = $this->item->fresh()->getAttributes();
        $primaryId = $this->item->choices()->sole()->getKey();

        $this->get($this->url('edit'))->assertOk()->assertSee('Preview Score Changes');
        $preview = $this->post($this->url('preview'), ['alternative_answer' => ' Humanistic psychologist ']);
        $preview->assertOk()->assertViewHas('preview', fn ($data) => $data['students_count'] === 2 && $data['attempts_count'] === 3)
            ->assertSee('Save Alternative and Apply to All Attempts');
        $this->assertDatabaseCount('assessment_item_choices', 1);

        $this->post($this->url('store'), [
            'preview_token' => $preview->viewData('previewToken'),
            'question_text' => 'Injected replacement', 'points' => 99, 'item_type' => 'essay',
            'alternative_answer' => 'Unreviewed alternative',
        ])->assertRedirect($this->url('edit'))->assertSessionHasNoErrors();

        $this->assertSame($before, DB::table('submission_answers')->orderBy('submission_answer_id')->get()->toJson());
        $this->assertSame($questionBefore, $this->item->fresh()->getAttributes());
        $this->assertDatabaseHas('assessment_item_choices', ['assessment_item_choice_id' => $primaryId, 'choice_text' => 'Humanistic psychologists']);
        $this->assertDatabaseMissing('assessment_item_choices', ['choice_text' => 'Unreviewed alternative']);
        foreach ([$first, $second, $third, $correct, $pending] as $answer) {
            $this->assertSame(1.0, $this->score($answer));
        }
        $this->assertSame(0.0, $this->score($wrong));

        $audit = AuditLog::where('action', 'ADD_ACCEPTED_ANSWER')->sole();
        $this->assertSame($this->teacher->getKey(), $audit->user_id);
        $this->assertSame($this->item->getKey(), $audit->auditable_id);
        $this->assertSame(['Humanistic psychologists'], $audit->metadata['accepted_answers_before']);
        $this->assertSame(['Humanistic psychologists', 'Humanistic psychologist'], $audit->metadata['accepted_answers_after']);
        $this->assertSame(3, $audit->metadata['affected_attempts_count']);
    }

    public function test_updated_credit_reaches_grading_results_and_item_analysis(): void
    {
        $answer = $this->answer('Humanistic psychologist');
        $this->get(route('instructor.assessments.submissions.grade', $answer->submission))->assertOk()
            ->assertSee($this->url('edit'), false)->assertViewHas('scoreText', '0');
        $this->saveAlternative();
        $this->get(route('instructor.assessments.submissions.grade', $answer->submission))->assertOk()->assertViewHas('scoreText', '1');
        $this->get(route('instructor.assessments.results', $this->published))->assertOk()
            ->assertViewHas('studentResults', fn ($rows) => $rows->first()['best_attempt']['score_value'] === 1.0);

        $published = $this->published->fresh()->load(['assessment.items.choices', 'submissions.answers.choice']);
        $this->assertSame('1', ReportAnalytics::summarize($published)['mean_score']);
        $analysis = ReportItemAnalysis::summarize($published->assessment->items, $published->submissions);
        $this->assertSame(1, $analysis[0]['correct_count']);
        $this->assertSame(100.0, $analysis[0]['correct_rate']);
    }

    public function test_corrections_do_not_propagate_to_original_or_separately_published_copies(): void
    {
        $original = $this->assessment->replicate();
        $original->public_id = null;
        $original->status = Assessment::STATUS_READY;
        $original->save();
        $copy = $this->item->replicate();
        $copy->public_id = null;
        $copy->assessment_id = $original->getKey();
        $copy->save();
        $copy->choices()->create(['choice_text' => 'Humanistic psychologists', 'is_correct' => true, 'sort_order' => 1]);
        $this->saveAlternative();
        $this->assertSame(['Humanistic psychologists'], $copy->choices()->pluck('choice_text')->all());
        $this->assertDatabaseCount('assessment_item_choices', 3);
    }

    public function test_all_publications_sharing_a_legacy_question_are_included_in_preview(): void
    {
        $otherPublication = $this->published->replicate();
        $otherPublication->public_id = null;
        $otherPublication->save();
        $answer = $this->answer('Humanistic psychologist');
        $answer->submission->update(['publish_assessment_id' => $otherPublication->getKey()]);
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $preview->assertOk()->assertViewHas('preview', fn ($data) => $data['attempts_count'] === 1);
        $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertSessionHasNoErrors();
        $this->assertSame(1.0, $this->score($answer));
    }

    public function test_other_instructors_and_students_cannot_preview_or_save_corrections(): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        foreach ([$this->account(['instructor']), $this->account(['student'])] as $other) {
            $this->signInAccount($other);
            $this->get($this->url('edit'))->assertForbidden();
            $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist'])->assertForbidden();
            $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertForbidden();
        }
        $this->assertDatabaseCount('assessment_item_choices', 1);
    }

    public function test_parent_mismatch_drafts_and_other_question_types_are_rejected(): void
    {
        $otherItem = $this->item->replicate();
        $otherItem->public_id = null;
        $otherItem->assessment_id = 999;
        $otherItem->save();
        foreach (['edit', 'preview', 'store'] as $action) {
            $url = route('instructor.assessments.items.accepted-answers.'.$action, [$this->assessment, $otherItem]);
            ($action === 'edit' ? $this->get($url) : $this->post($url))->assertNotFound();
        }
        $this->item->update(['item_type' => 'multiple_choice']);
        $this->post($this->url('preview'), ['alternative_answer' => 'Anything'])->assertNotFound();
        $this->item->update(['item_type' => 'identification']);
        $this->published->update(['publish_status' => PublishAssessment::STATUS_DRAFT]);
        $this->post($this->url('preview'), ['alternative_answer' => 'Anything'])->assertNotFound();
        $this->published->delete();
        $this->post($this->url('preview'), ['alternative_answer' => 'Anything'])->assertNotFound();
    }

    public function test_numeric_and_malformed_urls_are_rejected(): void
    {
        $base = '/instructor/assessments/'.$this->assessment->getKey().'/items/'.$this->item->getKey().'/accepted-answers';
        $this->get($base)->assertNotFound();
        $this->post($base, ['alternative_answer' => 'Anything'])->assertNotFound();
        $this->get(str_replace('/'.$this->assessment->getKey().'/', '/invalid/', $base))->assertNotFound();
    }

    public function test_blank_duplicate_and_oversized_alternatives_are_rejected(): void
    {
        foreach (['', '   ', '  HUMANISTIC PSYCHOLOGISTS ', str_repeat('a', 1001), ['invalid']] as $value) {
            $this->post($this->url('preview'), ['alternative_answer' => $value])->assertSessionHasErrors('alternative_answer');
        }
        $this->assertDatabaseCount('assessment_item_choices', 1);
    }

    public function test_limit_includes_existing_accepted_answers(): void
    {
        for ($index = 2; $index <= 10; $index++) {
            $this->item->choices()->create(['choice_text' => 'Alternative '.$index, 'is_correct' => true, 'sort_order' => $index]);
        }
        $this->post($this->url('preview'), ['alternative_answer' => 'Eleventh answer'])->assertSessionHasErrors('alternative_answer');
        $this->assertDatabaseCount('assessment_item_choices', 10);
    }

    public function test_save_requires_an_untampered_unexpired_preview_for_this_item(): void
    {
        $this->post($this->url('store'), ['alternative_answer' => 'Humanistic psychologist'])->assertSessionHasErrors('preview_token');
        $this->post($this->url('store'), ['preview_token' => 'invalid'])->assertSessionHasErrors('preview_token');
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $token = $preview->viewData('previewToken');
        $this->post($this->url('store'), ['preview_token' => $token.'tampered'])->assertSessionHasErrors('preview_token');
        $this->travel(21)->minutes();
        $this->post($this->url('store'), ['preview_token' => $token])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('assessment_item_choices', 1);
    }

    public function test_stale_key_or_changed_submissions_require_another_preview(): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $this->item->choices()->create(['choice_text' => 'Humanist', 'is_correct' => true, 'sort_order' => 2]);
        $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertSessionHasErrors('preview_token');

        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $this->answer('Humanistic psychologist');
        $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('assessment_item_choices', 2);
    }

    public function test_saving_twice_does_not_duplicate_the_alternative(): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $token = $preview->viewData('previewToken');
        $this->post($this->url('store'), ['preview_token' => $token])->assertSessionHasNoErrors();
        $this->post($this->url('store'), ['preview_token' => $token])->assertSessionHasErrors();
        $this->assertDatabaseCount('assessment_item_choices', 2);
        $this->assertSame(1, AuditLog::where('action', 'ADD_ACCEPTED_ANSWER')->count());
    }

    public function test_audit_failure_rolls_back_the_added_answer(): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        Event::listen('eloquent.creating: '.AuditLog::class, function (): void {
            throw new \RuntimeException('Audit unavailable');
        });
        $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertServerError();
        $this->assertDatabaseCount('assessment_item_choices', 1);
    }

    public function test_a_zero_alternative_is_accepted_and_scored(): void
    {
        $answer = $this->answer('0');
        $differentAnswer = $this->answer('Behaviorist');
        $this->saveAlternative('0');
        $this->assertSame(1.0, $this->score($answer));
        $this->assertSame(0.0, $this->score($differentAnswer));
    }

    public function test_preview_token_cannot_be_used_for_another_owned_question(): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => 'Humanistic psychologist']);
        $otherItem = $this->assessment->items()->create([
            'question_text' => 'Different question', 'item_type' => 'identification', 'points' => 2, 'sort_order' => 31,
        ]);
        $otherItem->choices()->create(['choice_text' => 'Primary', 'is_correct' => true, 'sort_order' => 1]);
        $url = route('instructor.assessments.items.accepted-answers.store', [$this->assessment, $otherItem]);
        $this->post($url, ['preview_token' => $preview->viewData('previewToken')])->assertSessionHasErrors('preview_token');
        $this->assertSame(1, $otherItem->choices()->count());
    }

    public function test_normal_published_question_editing_stays_locked(): void
    {
        $this->put(route('instructor.assessments.items.update', [$this->assessment, $this->item]), [
            'question_text' => 'Replacement question', 'points' => 5, 'accepted_answers' => ['Replacement'],
        ])->assertSessionHasErrors('assessment');
        $this->assertSame('They believe in self-improvement.', $this->item->fresh()->question_text);
        $this->assertSame('1.00', $this->item->fresh()->points);
    }

    public function test_published_question_page_exposes_the_correction_action(): void
    {
        $this->get(route('instructor.assessments.show', $this->assessment))->assertOk()
            ->assertSee($this->url('edit'), false)->assertSee('Add Accepted Alternative');
    }

    public function test_students_see_recomputed_credit_without_submitting_again(): void
    {
        $student = $this->account(['student']);
        $this->published->class->enrollStudent($student->studentProfile);
        $this->published->update(['publish_status' => PublishAssessment::STATUS_PUBLISHED, 'score_visibility' => true, 'answer_visibility' => true]);
        $answer = $this->answer('Humanistic psychologist', $student);
        $this->saveAlternative();
        $this->signInAccount($student);
        $this->get(route('student.assessments.submitted', $this->published))->assertOk()
            ->assertViewHas('scoreText', '1')
            ->assertViewHas('answerRows', fn ($rows) => $rows->first()['is_correct'] === true);
        $this->assertSame('Humanistic psychologist', $answer->fresh()->answer_text);
    }

    private function url(string $action): string
    {
        return route('instructor.assessments.items.accepted-answers.'.$action, [$this->assessment, $this->item]);
    }

    private function saveAlternative(string $alternative = 'Humanistic psychologist'): void
    {
        $preview = $this->post($this->url('preview'), ['alternative_answer' => $alternative])->assertOk();
        $this->post($this->url('store'), ['preview_token' => $preview->viewData('previewToken')])->assertSessionHasNoErrors();
    }

    private function answer(string $text, ?User $student = null, int $attempt = 1, string $status = Submission::STATUS_SUBMITTED): SubmissionAnswer
    {
        $student ??= $this->account(['student']);
        $submission = Submission::create([
            'publish_assessment_id' => $this->published->getKey(), 'student_profile_id' => $student->studentProfile->getKey(),
            'attempt_number' => $attempt, 'status' => $status, 'submitted_at' => $status === Submission::STATUS_SUBMITTED ? now() : null,
        ]);

        return $submission->answers()->create(['assessment_item_id' => $this->item->getKey(), 'answer_text' => $text]);
    }

    private function score(SubmissionAnswer $answer): float
    {
        return AssessmentScoring::earnedPoints($this->item->fresh()->load('choices'), $answer->fresh()->load('choice'));
    }
}
