<?php

namespace Tests\Feature;

use App\Http\Controllers\DepartmentChair\ReportController as ChairReportController;
use App\Http\Controllers\Instructor\ReportController as InstructorReportController;
use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\AssessmentItemChoice;
use App\Models\ClassDetail;
use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Support\ReportAnalytics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Schema::create('passing_rate_settings', function (Blueprint $table): void {
            $table->id();
            $table->integer('year_level');
            $table->float('passing_rate');
        });
        DB::table('passing_rate_settings')->insert(['year_level' => 2, 'passing_rate' => 75]);
        PassingRateSetting::clearRateCache();
    }

    protected function tearDown(): void
    {
        PassingRateSetting::clearRateCache();
        parent::tearDown();
    }

    public function test_faculty_and_chair_reports_keep_the_same_best_attempt_scores_and_year_level_passing_rate(): void
    {
        $published = $this->assessment([
            $this->submission(1, 1, [false, false]),
            $this->submission(1, 2, [true, true]),
            $this->submission(2, 1, [true, false]),
            $this->submission(3, 1, [true, true], Submission::STATUS_IN_PROGRESS),
            $this->submission(4, 1, [true, true], Submission::STATUS_SUBMITTED, Submission::COMPLETION_WARNING_LIMIT),
        ]);
        $expected = [
            'students_count' => 4, 'takers_count' => 2, 'item_count' => 2,
            'highest_score' => '2', 'lowest_score' => '1', 'mean_score' => '1.5',
            'mean_percentage' => 75.0, 'passing_rate' => 50.0, 'max_score' => '2',
        ];
        $this->assertSame($expected, ReportAnalytics::summarize($published));

        foreach ([ChairReportController::class => 'reportAnalytics', InstructorReportController::class => 'publishAssessmentReportAnalytics'] as $class => $method) {
            $summary = (new ReflectionMethod($class, $method))->invoke(new $class, $published);
            $this->assertSame($expected, array_intersect_key($summary, $expected));
        }
    }

    public function test_no_eligible_submissions_keep_zero_scores_and_rates(): void
    {
        $summary = ReportAnalytics::summarize($this->assessment([
            $this->submission(1, 1, [true, true], Submission::STATUS_IN_PROGRESS),
        ]));

        $this->assertSame(0, $summary['takers_count']);
        foreach (['highest_score', 'lowest_score', 'mean_score'] as $field) {
            $this->assertSame('0', $summary[$field]);
        }
        $this->assertSame(0, $summary['mean_percentage']);
        $this->assertSame(0, $summary['passing_rate']);
    }

    private function assessment(array $submissions): PublishAssessment
    {
        $items = collect([1, 2])->map(function (int $number): AssessmentItem {
            $item = new AssessmentItem(['item_type' => 'identification', 'points' => 1, 'sort_order' => $number]);
            $item->assessment_item_id = $number;
            $item->setRelation('choices', collect([new AssessmentItemChoice(['choice_text' => 'yes', 'is_correct' => true])]));

            return $item;
        });
        $class = new class(['year_level' => 2]) extends AcademicClass
        {
            public function enrolledStudentsCount(): int
            {
                return 4;
            }
        };
        $assessment = (new Assessment)->setRelation('items', $items);
        $detail = (new ClassDetail)->setRelation('class', $class);

        return (new PublishAssessment)->setRelation('assessment', $assessment)
            ->setRelation('classDetail', $detail)->setRelation('submissions', collect($submissions));
    }

    private function submission(int $student, int $attempt, array $correct, string $status = Submission::STATUS_SUBMITTED, ?string $reason = null): Submission
    {
        $answers = collect($correct)->map(fn (bool $value, int $index): SubmissionAnswer => (new SubmissionAnswer(['assessment_item_id' => $index + 1, 'answer_text' => $value ? 'yes' : 'no']))->setRelation('choice', null));

        return (new Submission(['student_profile_id' => $student, 'attempt_number' => $attempt, 'status' => $status, 'completion_reason' => $reason]))
            ->setRelation('answers', $answers);
    }
}
