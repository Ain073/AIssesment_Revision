<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\ClassDetail;
use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Services\ReportAiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportAiServiceTest extends TestCase
{
    private const MOST_LEARNED = 'Students demonstrated understanding of the assessed theories.';

    private const LEAST_LEARNED = 'Students need to strengthen their explanations of the assessed theories.';

    private const QUESTION = 'Contrast the view that the thinking mind is separate from the physical body with the view that the self consists of changing perceptions and has no permanent core.';

    private array $claudeResponses;

    protected function setUp(): void
    {
        parent::setUp();

        PassingRateSetting::clearRateCache();
        Schema::create('passing_rate_settings', function (Blueprint $table): void {
            $table->unsignedInteger('year_level');
            $table->float('passing_rate');
        });

        config()->set('services.ai_report.providers', [
            'openai' => ['model' => 'test-model', 'key' => 'test-key'],
            'claude' => ['model' => 'test-model', 'key' => 'test-key'],
        ]);

        $text = json_encode([
            'concepts_most_learned_skills' => self::MOST_LEARNED,
            'concepts_least_learned_skills' => self::LEAST_LEARNED,
        ]);
        $this->claudeResponses = [
            ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $text]]],
        ];

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['output_text' => $text]),
            'api.anthropic.com/v1/messages' => fn () => Http::response(array_shift($this->claudeResponses)),
        ]);
    }

    protected function tearDown(): void
    {
        PassingRateSetting::clearRateCache();

        parent::tearDown();
    }

    public function test_perfect_scores_cannot_produce_a_learning_gap_from_either_provider(): void
    {
        foreach (['openai', 'claude'] as $provider) {
            $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[1, 1], [1, 1]]), $provider);

            $this->assertSame(self::MOST_LEARNED, $draft['concepts_most_learned_skills']);
            $this->assertSame(
                'No least learned concept or skill was identified because all students earned full marks on every assessed item.',
                $draft['concepts_least_learned_skills'],
            );
            $this->assertSame($provider, $draft['source']);
        }

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $data = $this->requestData($request);

            return $data['items'][0]['question'] === self::QUESTION
                && $data['items'][0]['correct_rate'] === 100
                && $data['takers_count'] === 2;
        });
    }

    public function test_pending_essays_cannot_produce_claims_about_learning(): void
    {
        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[null, null]]), 'openai');

        $this->assertSame(
            'Essay grading is pending. The most learned concepts and skills cannot yet be determined.',
            $draft['concepts_most_learned_skills'],
        );
        $this->assertSame(
            'Essay grading is pending. The least learned concepts and skills cannot yet be determined.',
            $draft['concepts_least_learned_skills'],
        );
    }

    public function test_perfect_graded_items_do_not_hide_other_items_pending_grading(): void
    {
        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[1, null]]), 'claude');

        $this->assertSame(self::LEAST_LEARNED, $draft['concepts_least_learned_skills']);
        Http::assertSent(function (Request $request): bool {
            $data = $this->requestData($request);

            return $data['items'][1]['correct_rate'] === null
                && $data['items'][1]['pending_count'] === 1
                && count($data['weakest_items']) === 1;
        });
    }

    public function test_zero_scores_cannot_produce_a_claim_of_full_credit_performance(): void
    {
        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[0, 0]]), 'openai');

        $this->assertSame(
            'No assessed concept or skill met the full-credit standard in the available graded results. This does not rule out partial understanding.',
            $draft['concepts_most_learned_skills'],
        );
        Http::assertSent(function (Request $request): bool {
            $item = $this->requestData($request)['items'][0];

            return $item['incorrect_count'] === 1
                && $item['unanswered_count'] === 0
                && $item['pending_count'] === 0;
        });
    }

    public function test_partial_essay_scores_are_not_described_as_no_understanding(): void
    {
        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[0.5, 0.5]]), 'claude');

        $this->assertStringContainsString('This does not rule out partial understanding.', $draft['concepts_most_learned_skills']);
        Http::assertSent(function (Request $request): bool {
            $data = $this->requestData($request);

            return $data['mean_score'] === 1
                && $data['items'][0]['incorrect_count'] === 1
                && $data['items'][0]['correct_rate'] === 0;
        });
    }

    public function test_no_takers_cannot_produce_claims_about_learning(): void
    {
        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([]), 'claude');

        $this->assertSame(
            'Assessment results are unavailable to identify the most learned concepts and skills.',
            $draft['concepts_most_learned_skills'],
        );
        $this->assertSame(
            'Assessment results are unavailable to identify the least learned concepts and skills.',
            $draft['concepts_least_learned_skills'],
        );
    }

    public function test_claude_requests_structured_json_with_room_for_both_paragraphs(): void
    {
        app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');

        Http::assertSent(function (Request $request): bool {
            $format = $request['output_config']['format'];

            return $request['max_tokens'] === 1024
                && $format['type'] === 'json_schema'
                && $format['schema']['type'] === 'object'
                && $format['schema']['additionalProperties'] === false
                && $format['schema']['required'] === ['concepts_most_learned_skills', 'concepts_least_learned_skills']
                && $format['schema']['properties'] === [
                    'concepts_most_learned_skills' => ['type' => 'string'],
                    'concepts_least_learned_skills' => ['type' => 'string'],
                ];
        });
        Http::assertSentCount(1);
    }

    public function test_claude_reads_all_text_blocks_and_ignores_other_blocks(): void
    {
        $text = $this->claudeResponses[0]['content'][0]['text'];
        $split = intdiv(strlen($text), 2);
        $this->claudeResponses = [[
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'thinking', 'thinking' => 'Not report content.'],
                ['type' => 'text', 'text' => "```json\n".substr($text, 0, $split)],
                ['type' => 'text', 'text' => substr($text, $split)."\n```"],
            ],
        ]];

        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');

        $this->assertSame(self::MOST_LEARNED, $draft['concepts_most_learned_skills']);
        $this->assertSame(self::LEAST_LEARNED, $draft['concepts_least_learned_skills']);
    }

    public function test_claude_retries_truncated_output_once_with_a_higher_limit(): void
    {
        array_unshift($this->claudeResponses, [
            'stop_reason' => 'max_tokens',
            'usage' => ['output_tokens' => 1024],
            'content' => [['type' => 'text', 'text' => '{"concepts_most_learned_skills":"Incomplete']],
        ]);

        $draft = app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');

        $this->assertSame(self::MOST_LEARNED, $draft['concepts_most_learned_skills']);
        $this->assertSame(self::LEAST_LEARNED, $draft['concepts_least_learned_skills']);
        Http::assertSentCount(2);
        $requests = Http::recorded()->pluck(0);
        $this->assertSame([1024, 2048], $requests->map(fn (Request $request): int => $request['max_tokens'])->all());
        $this->assertSame($requests[0]['messages'], $requests[1]['messages']);
        $this->assertSame($requests[0]['output_config'], $requests[1]['output_config']);
    }

    public function test_claude_does_not_accept_truncated_output_or_retry_indefinitely(): void
    {
        $this->claudeResponses[0]['stop_reason'] = 'max_tokens';
        $this->claudeResponses[] = $this->claudeResponses[0];

        try {
            app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');
            $this->fail('Truncated output must not become a report draft, even if its JSON looks complete.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Claude report draft remained incomplete after retrying with a higher output token limit.',
                $exception->getPrevious()?->getMessage(),
            );
        }

        Http::assertSentCount(2);
    }

    public function test_claude_refusal_is_rejected_without_retrying(): void
    {
        $this->claudeResponses[0]['stop_reason'] = 'refusal';

        try {
            app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');
            $this->fail('A refusal must not become a report draft.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Claude declined to generate the report draft.', $exception->getPrevious()?->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_invalid_claude_json_logs_metadata_without_logging_report_content(): void
    {
        Log::spy();
        $this->claudeResponses = [[
            'stop_reason' => 'end_turn',
            'usage' => ['output_tokens' => 20],
            'content' => [['type' => 'text', 'text' => 'Private report content, not JSON.']],
        ]];

        try {
            app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');
            $this->fail('Invalid JSON must not become a report draft.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('AI response was not valid JSON.', $exception->getPrevious()?->getMessage());
        }

        Log::shouldHaveReceived('warning')->with('Claude report draft response failed validation.', [
            'model' => 'test-model',
            'stop_reason' => 'end_turn',
            'output_tokens' => 20,
            'text_length' => strlen('Private report content, not JSON.'),
        ])->once();
        Http::assertSentCount(1);
    }

    public function test_malformed_report_fields_are_rejected_instead_of_becoming_blank_drafts(): void
    {
        $invalidDrafts = [
            [],
            ['concepts_most_learned_skills' => self::MOST_LEARNED],
            ['concepts_most_learned_skills' => 1, 'concepts_least_learned_skills' => self::LEAST_LEARNED],
            ['concepts_most_learned_skills' => [], 'concepts_least_learned_skills' => self::LEAST_LEARNED],
            ['concepts_most_learned_skills' => null, 'concepts_least_learned_skills' => self::LEAST_LEARNED],
            ['concepts_most_learned_skills' => self::MOST_LEARNED, 'concepts_least_learned_skills' => '   '],
            ['concepts_most_learned_skills' => self::MOST_LEARNED, 'concepts_least_learned_skills' => self::LEAST_LEARNED, 'extra' => 'Unexpected'],
        ];

        foreach ($invalidDrafts as $invalidDraft) {
            $this->claudeResponses = [[
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => json_encode($invalidDraft)]],
            ]];

            try {
                app(ReportAiService::class)->generate($this->publishedAssessment([[1, 0]]), 'claude');
                $this->fail('Malformed report fields must not become a report draft.');
            } catch (\RuntimeException $exception) {
                $this->assertStringStartsWith('AI response must contain', $exception->getPrevious()?->getMessage() ?? '');
            }
        }

        Http::assertSentCount(count($invalidDrafts));
    }

    private function requestData(Request $request): array
    {
        $prompt = $request['input'] ?? $request['messages'][0]['content'];

        return json_decode(Str::after($prompt, "Assessment Data:\n"), true, flags: JSON_THROW_ON_ERROR);
    }

    private function publishedAssessment(array $scores): PublishAssessment
    {
        $items = collect([1, 2])->map(function (int $number): AssessmentItem {
            $item = new AssessmentItem([
                'sort_order' => $number, 'item_type' => 'essay', 'question_text' => self::QUESTION, 'points' => 1,
            ]);
            $item->assessment_item_id = $number;
            $item->setRelation('choices', collect());

            return $item;
        });
        $assessment = new Assessment(['title' => 'Essay assessment', 'report_category' => 'formative']);
        $assessment->setRelation('items', $items);
        $assessment->setRelation('subject', null);
        $classDetail = new ClassDetail;
        $classDetail->setRelation('class', null);
        $submissions = collect($scores)->map(function (array $points, int $student): Submission {
            $answers = collect($points)->map(function (?float $score, int $index): SubmissionAnswer {
                $answer = new SubmissionAnswer([
                    'assessment_item_id' => $index + 1,
                    'answer_text' => 'A submitted essay response.',
                    'earned_points' => $score,
                ]);
                $answer->setRelation('choice', null);

                return $answer;
            });
            $submission = new Submission([
                'student_profile_id' => $student + 1, 'attempt_number' => 1, 'status' => Submission::STATUS_SUBMITTED,
            ]);
            $submission->setRelation('answers', $answers);

            return $submission;
        });
        $published = new PublishAssessment;
        $published->setRelation('assessment', $assessment);
        $published->setRelation('classDetail', $classDetail);
        $published->setRelation('submissions', $submissions);

        return $published;
    }
}
