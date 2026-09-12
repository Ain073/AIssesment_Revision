<?php

namespace App\Services;

use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportAiService
{
    public function generate(PublishAssessment $publishAssessment, ?string $provider = null): array
    {
        $data = $this->reportData($publishAssessment);
        $provider = $provider ?: (string) config('services.ai_report.provider', 'mock');
        $settings = $this->providerSettings($provider);
        $model = (string) ($settings['model'] ?? '');
        $key = (string) ($settings['key'] ?? '');

        if ($provider === 'mock' || $model === '' || $key === '') {
            throw new \RuntimeException("{$this->providerLabel($provider)} is not fully configured. Please check the model and API key.");
        }

        try {
            if ($provider === 'openai') {
                return $this->openAiDraft($data, $model, $key);
            }

            if ($provider === 'claude') {
                return $this->claudeDraft($data, $model, $key);
            }

            throw new \RuntimeException('The selected AI provider is not supported.');
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            $apiMessage = $this->apiErrorMessage($exception);

            Log::warning('AI report draft request failed.', [
                'provider' => $provider,
                'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                'status' => $status,
                'message' => $exception->getMessage(),
                'api_message' => $apiMessage,
            ]);

            throw new \RuntimeException(
                "{$this->providerLabel($provider)} request failed"
                    .($status ? " ({$status})" : '')
                    .': '.$apiMessage,
                previous: $exception,
            );
        } catch (\Throwable $exception) {
            Log::warning('AI report draft failed.', [
                'provider' => $provider,
                'publish_assessment_id' => $publishAssessment->publish_assessment_id,
                'message' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(
                'AI draft could not be generated. Please try again or check the AI settings.',
                previous: $exception,
            );
        }
    }

    public function candidateOptions(): array
    {
        return collect((array) config('services.ai_report.providers', []))
            ->map(fn (array $settings, string $provider): array => [
                'value' => $provider,
                'label' => (string) ($settings['label'] ?? Str::headline($provider)),
            ])
            ->values()
            ->all();
    }

    private function providerSettings(string $provider): array
    {
        $settings = (array) config("services.ai_report.providers.$provider", []);

        if ($settings !== []) {
            return $settings;
        }

        return [
            'label' => $this->providerLabel($provider),
            'model' => config('services.ai_report.model'),
            'key' => config('services.ai_report.key'),
        ];
    }

    private function providerLabel(string $provider): string
    {
        return (string) config("services.ai_report.providers.$provider.label", Str::headline($provider ?: 'AI provider'));
    }

    private function apiErrorMessage(RequestException $exception): string
    {
        $response = $exception->response;

        if (! $response) {
            return 'Network connection failed. Please check server internet access.';
        }

        $payload = $response->json();
        $message = data_get($payload, 'error.message')
            ?? data_get($payload, 'error.error.message')
            ?? data_get($payload, 'message')
            ?? $response->body()
            ?? 'Please check the API key, billing credits, and model access.';

        return Str::limit(trim((string) $message), 240);
    }

    private function openAiDraft(array $data, string $model, string $key): array
    {
        $response = Http::withToken($key)
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => $this->prompt($data),
                'max_output_tokens' => 500,
            ])
            ->throw()
            ->json();

        return $this->parseDraft($this->openAiText($response), 'openai');
    }

    private function claudeDraft(array $data, string $model, string $key): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 500,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->prompt($data),
                    ],
                ],
            ])
            ->throw()
            ->json();

        return $this->parseDraft($response['content'][0]['text'] ?? '', 'claude');
    }

    private function reportData(PublishAssessment $publishAssessment): array
    {
        $publishAssessment->loadMissing([
            'assessment.items.choices',
            'assessment.subject',
            'classDetail.class.subject',
            'submissions.answers.choice',
        ]);

        $assessment = $publishAssessment->assessment;
        $items = $assessment?->items ?? collect();
        $submissions = $publishAssessment->submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->values();
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $scores = $submissions
            ->groupBy('student_profile_id')
            ->map(fn (Collection $studentSubmissions): float => (float) $studentSubmissions
                ->map(fn (Submission $submission): float => $this->submissionScore($submission, $items))
                ->max())
            ->values();

        return [
            'assessment_title' => (string) ($assessment?->title ?? 'Assessment'),
            'subject' => trim(($assessment?->subject?->subject_code ?? '').' '.($assessment?->subject?->subject_name ?? '')),
            'report_category' => (string) ($assessment?->report_category ?? 'general'),
            'reporting_term' => (string) ($assessment?->reporting_term ?? 'general'),
            'students_count' => $publishAssessment->class?->enrolledStudentsCount() ?? 0,
            'takers_count' => $scores->count(),
            'items_count' => $items->count(),
            'max_score' => $maxScore,
            'mean_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : 0,
            'passing_rate' => $this->passingRate($scores, $maxScore),
            'items' => $this->itemSummaries($items, $submissions),
        ];
    }

    private function itemSummaries(Collection $items, Collection $submissions): array
    {
        return $items->map(function ($item) use ($submissions): array {
            $correctCount = $submissions->filter(function (Submission $submission) use ($item): bool {
                $answer = $submission->answers->firstWhere('assessment_item_id', $item->assessment_item_id);

                return $answer ? AssessmentScoring::isCorrect($item, $answer) : false;
            })->count();
            $total = $submissions->count();

            return [
                'question' => Str::limit((string) $item->question_text, 120),
                'correct_count' => $correctCount,
                'response_count' => $total,
                'correct_rate' => $total > 0 ? round(($correctCount / $total) * 100, 2) : 0,
            ];
        })->values()->all();
    }

    private function prompt(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are helping an instructor prepare draft narrative cells for a school performance monitoring report.

Use only the assessment data below. Write in a formal but natural academic reporting style. The output is a draft that the instructor will review, edit, and finalize.

Return valid JSON only with exactly these two string fields:
1. concepts_most_learned_skills
2. concepts_least_learned_skills

Evidence rules:
- Base every statement only on the assessment data, including performance patterns, assessment items, item results, scores, and concepts or skills connected to those items.
- Do not invent reasons for performance. Do not claim students did not study, lacked motivation, were not taught properly, had poor attendance, or experienced a specific learning problem unless the data explicitly says so.
- Avoid unsupported student counts, names, percentages, statistics, or causal explanations.
- Avoid wording that implies unsupported causes or strong statistical conclusions. Do not use phrases such as "significant drop", "diverted focus", "lack of effort", "poor preparation", "clearly proves", or similar explanations unless the data explicitly supports them.
- Prefer cautious academic phrasing such as "lower performance was observed", "the results suggest", "may require further reinforcement", or "may benefit from additional guided practice".
- Explain what the results mean academically instead of simply repeating numerical values.
- Use cautious evidence-based wording when the data set is small or limited.

Most learned section:
- Explain areas where students showed stronger performance.
- Discuss concepts students understood well, skills applied correctly, competencies demonstrated, or patterns of strong performance across related items.
- Do not only identify the highest-scoring topic. Interpret what students were generally able to understand, recognize, apply, analyze, or perform.

Least learned section:
- Explain areas where students showed weaker performance.
- Discuss concepts where difficulty appeared, skills applied incorrectly or inconsistently, patterns of lower performance, or competencies that may require reinforcement.
- Do not exaggerate the result or make unsupported conclusions.
- Include a recommendation only when it is useful and supported by the assessment results.

Report tone:
- If report_category indicates a formative report, use a developmental tone focused on current strengths, developing skills, reinforcement, and possible areas for additional practice in succeeding lessons or activities.
- If report_category indicates a summative report, use an overall performance tone focused on demonstrated mastery, stronger areas of achievement, lower-performing concepts or skills, and areas that may still need reinforcement after the assessment period.
- If the report category is unclear, use a balanced academic reporting tone.

Writing style:
- Write each field as a short narrative paragraph, not a list of topics, scores, or percentages.
- Keep each field to 2 to 4 concise sentences suitable for a narrow report table cell.
- Do not include markdown, bullets, labels, or headings inside the JSON values.
- Do not write like an answer key or quiz explanation.
- Summarize item results into broader concepts or skills when the data supports it.
- Do not focus on only one exact answer phrase unless the data only supports that topic.
- The paragraph should sound written specifically for the current assessment.

Writing variety:
- Do not begin with generic setup phrases such as "It is evident from the data", "Based on the assessment results", "The assessment results indicate", "It appears that", or "The data shows that".
- Do not repeatedly begin paragraphs with fixed phrases such as "Students demonstrated", "Students showed difficulty", or "The results suggest".
- These phrases may be used when appropriate, but they must not become the default opening.
- Vary paragraph openings, sentence structure, transitions, order of ideas, conclusion style, and wording naturally based on the data.
- Do not only perform synonym replacement. Vary the flow and organization of ideas when appropriate.
- Avoid repetitive conclusions, overly generic statements, mechanical sentence patterns, and wording copied from previous reports.

Reference tone only. Do not copy or mechanically paraphrase:
{
  "concepts_most_learned_skills": "The class showed stronger performance on items involving foundational concepts and direct recall of lesson terms. Their responses suggest that many students can recognize key ideas and connect them with basic explanations when the questions are straightforward.",
  "concepts_least_learned_skills": "Lower performance appeared in items requiring application of concepts to practical or situational contexts. These areas may benefit from additional guided practice that helps students connect lesson terms with procedures, examples, or problem scenarios."
}

Assessment data:
$json
PROMPT;
    }

    private function parseDraft(string $text, string $source): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $json = $start !== false && $end !== false
            ? substr($text, $start, $end - $start + 1)
            : $text;
        $draft = json_decode($json, true);

        if (! is_array($draft)) {
            throw new \RuntimeException('AI response was not valid JSON.');
        }

        return [
            'concepts_most_learned_skills' => trim((string) ($draft['concepts_most_learned_skills'] ?? '')),
            'concepts_least_learned_skills' => trim((string) ($draft['concepts_least_learned_skills'] ?? '')),
            'source' => $source,
        ];
    }

    private function openAiText(array $response): string
    {
        if (isset($response['output_text'])) {
            return (string) $response['output_text'];
        }

        return collect($response['output'] ?? [])
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->pluck('text')
            ->filter()
            ->implode("\n");
    }

    private function passingRate(Collection $scores, float $maxScore): float
    {
        if ($scores->isEmpty() || $maxScore <= 0) {
            return 0;
        }

        $passingScore = $maxScore * 0.75;

        return round(($scores->filter(fn (float $score): bool => $score >= $passingScore)->count() / $scores->count()) * 100, 2);
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
    }
}
