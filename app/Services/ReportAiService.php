<?php

namespace App\Services;

use App\Models\ClassAssessment;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportAiService
{
    public function generate(ClassAssessment $classAssessment, ?string $provider = null): array
    {
        $data = $this->reportData($classAssessment);
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
                'class_assessment_id' => $classAssessment->class_assessment_id,
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
                'class_assessment_id' => $classAssessment->class_assessment_id,
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

    private function reportData(ClassAssessment $classAssessment): array
    {
        $classAssessment->loadMissing([
            'assessment.items.choices',
            'assessment.subject',
            'class.subject',
            'submissions.answers.choice',
        ]);

        $assessment = $classAssessment->assessment;
        $items = $assessment?->items ?? collect();
        $submissions = $classAssessment->submissions
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
            'students_count' => (int) ($classAssessment->class?->students?->count() ?? 0),
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
You are helping an instructor prepare the narrative cells of a school performance monitoring report.

Use only the assessment data below. Write in a natural instructor-assisted academic tone, like a teacher preparing a concise performance monitoring report.

Rules:
- Return valid JSON only.
- Write only these two fields:
  1. concepts_most_learned_skills
  2. concepts_least_learned_skills
- Do not write like an answer key or quiz explanation.
- Do not focus on only one exact answer phrase unless the data only supports that topic.
- Summarize the item results into broader concepts or skills.
- For most learned, use the highest-performing item topics and describe demonstrated competencies.
- For least learned, use the lowest-performing item topics and describe areas that need reinforcement.
- Write as a human-readable paragraph, not as a rigid template.
- Vary the sentence construction naturally based on the results.
- Use phrases such as "students demonstrated", "students showed", "most students correctly answered", "students need to strengthen", or "difficulty was observed" only when they fit the data.
- For most learned, prefer natural wording such as "students correctly answered questions related to..." or "students demonstrated understanding of..." when the results point to mastered topics.
- For least learned, prefer natural wording such as "students showed difficulty in applying concepts to questions about..." when the results point to application or situational weaknesses.
- Avoid artificial phrases such as "relative strength", "well-understood grasp", or wording that sounds like an answer explanation.
- Do not copy the sample wording when the assessment data points to different concepts or skills.
- Do not start with disclaimers such as "Based on the limited responses" or "Despite the small sample size."
- If the data set is small, keep the wording cautious by using phrases such as "the results suggest" instead of making broad claims.
- Avoid unsupported student counts, names, or invented statistics.
- Keep each field to 2 to 4 concise sentences suitable for a narrow report table cell.
- Do not include markdown, bullets, or labels inside the JSON values.

Sample tone only:
{
  "concepts_most_learned_skills": "Students correctly answered questions related to the foundational concepts covered in the assessment. This shows that they can recall key terms and explain basic ideas when the questions are direct and theory-based.",
  "concepts_least_learned_skills": "Students showed difficulty in applying concepts to questions about practical or situational parts of the lesson. The results suggest that they need further support in connecting lesson terms with actual procedures, examples, or problem scenarios."
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
