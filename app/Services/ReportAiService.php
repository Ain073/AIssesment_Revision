<?php

namespace App\Services;

use App\Models\PassingRateSetting;
use App\Models\PublishAssessment;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use App\Support\ReportItemAnalysis;
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
                'input' => $this->prompt($data, 'openai'),
                'max_output_tokens' => 500,
                'temperature' => 0.7,
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
                'temperature' => 0.7,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->prompt($data, 'claude'),
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
        $submissions = $this->reportableSubmissions($publishAssessment->submissions);
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $scores = $submissions
            ->groupBy('student_profile_id')
            ->map(fn (Collection $studentSubmissions): float => (float) $studentSubmissions
                ->map(fn (Submission $submission): float => $this->submissionScore($submission, $items))
                ->max())
            ->values();
        $itemSummaries = collect(ReportItemAnalysis::summarize($items, $submissions))
            ->map(fn (array $item): array => array_replace($item, ['question' => Str::limit($item['question'], 120)]));

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
            'passing_rate' => $this->passingRate($scores, $maxScore, $publishAssessment->class?->year_level),
            'items' => $itemSummaries->values()->all(),
            'strongest_items' => ReportItemAnalysis::strongestItems($itemSummaries),
            'weakest_items' => ReportItemAnalysis::weakestItems($itemSummaries),
        ];
    }

    private function reportableSubmissions(Collection $submissions): Collection
    {
        return $submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->reject(fn (Submission $submission): bool => $submission->completion_reason === Submission::COMPLETION_WARNING_LIMIT)
            ->values();
    }

    private function prompt(array $data, ?string $provider = null): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $subject = (string) ($data['subject'] ?? 'the course subject');
        $title = (string) ($data['assessment_title'] ?? 'Assessment');
        $category = (string) ($data['report_category'] ?? 'general');

        $providerEmphasis = match ($provider) {
            'claude' => 'Adopt an analytical and pedagogically reflective tone with deep curricular insights into conceptual grasp versus procedural application.',
            'openai' => 'Adopt a decisive, clear, and academically rigorous tone highlighting core learning competencies and actionable classroom interventions.',
            default => 'Adopt an authentic, sophisticated academic faculty tone.',
        };

        return <<<PROMPT
You are a distinguished university professor and academic evaluator drafting the narrative diagnostic sections for an official Institutional Student Performance Monitoring Report.

Role & Pedagogical Voice:
- Write in the authentic, polished, and authoritative voice of an expert college educator reviewing student performance in {$subject} for the assessment "{$title}".
- {$providerEmphasis}
- The text must sound as if an experienced faculty member personally composed it after evaluating student work—NOT like an automated tool, generic AI, or mechanical formula.
- NEVER start with formulaic clichés such as "Based on the assessment...", "The data indicates...", "The results show...", "According to the scores...", or "It is evident that...". Jump immediately into the subject-matter competencies, cognitive skills, and pedagogical substance.
- NEVER sound like an answer key or quiz rubric. Avoid citing item numbers (e.g., "Item 1", "Question 4") unless referencing a specific multi-part problem.
- Keep each field to 2 to 4 concise, high-impact sentences suitable for a standard academic performance report table.

Output Format:
Return valid JSON only with exactly these two keys:
{
  "concepts_most_learned_skills": "...",
  "concepts_least_learned_skills": "..."
}

Diagnostic Analysis Guidelines:
- Item counts use each student's highest-scoring submitted attempt. correct_rate is the percentage of takers with a fully correct answer; unanswered items remain in the denominator. A null correct_rate means no takers or grading is pending and must not support a mastery claim.
- strongest_items and weakest_items are relative rankings within this assessment. If all rates are equal, state that performance is uniform rather than inventing differences; a highest rate of zero is not evidence of mastery.

1. concepts_most_learned_skills (Demonstrated Competencies & Conceptual Mastery):
- Identify the key concepts or competencies represented by the highest-performing items (see strongest_items).
- Characterize the nature of student mastery: foundational recall, accurate procedural execution, sound contextual differentiation, or conceptual comprehension.
- Vary the opening phrasing naturally across different assessments. Examples of authentic educator phrasing:
  * "Learners demonstrated commendable mastery of [Concept], consistently exhibiting..."
  * "Strong conceptual clarity was apparent in topics addressing [Concept], where students accurately..."
  * "High proficiency emerged in competencies involving [Concept], reflecting solid grasp of..."
  * "Students displayed robust understanding when tasked with [Skill/Task], effectively synthesizing..."

2. concepts_least_learned_skills (Diagnostic Deficits & Targeted Instructional Interventions):
- Identify the specific concepts or competencies where students encountered the most friction (see weakest_items).
- Diagnostically pinpoint the core misconception or cognitive breakdown (e.g., conflating related definitions, difficulty applying theoretical principles to novel scenarios, or struggling with multi-step analytical reasoning).
- Close with a tailored, actionable pedagogical recommendation that an expert teacher would implement (e.g., targeted comparative matrix review, illustrative worked examples, formative checkpoint drills, or concept-mapping exercises).
- Vary the opening phrasing naturally. Examples of authentic educator phrasing:
  * "Notable misconceptions persisted in topics concerning [Concept], particularly when students were asked to..."
  * "Diagnostic analysis reveals difficulty with [Concept], indicating that higher-order application remains an area requiring instructional reinforcement. Incorporating [Strategy] will help..."
  * "Gaps were most pronounced in competencies requiring [Skill], where students frequently confused [Concept A] with [Concept B]. A dedicated recap utilizing [Strategy] is recommended."
  * "Performance dipped on questions evaluating [Concept], suggesting that students struggled with... To address this, [Intervention] will solidify understanding before succeeding modules."

Curriculum & Assessment Context:
- Subject & Assessment: Ground all observations directly in the provided subject ({$subject}) and assessment content ("{$title}").
- Assessment Category ({$category}):
  * If 'formative': emphasize diagnostic insights, learning momentum, and immediate remedial interventions for upcoming class sessions.
  * If 'summative': emphasize cumulative achievement, mastery standards, and durable competencies needing bridging before advancement.

Assessment Data:
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

    private function passingRate(Collection $scores, float $maxScore, ?int $yearLevel): float
    {
        if ($scores->isEmpty() || $maxScore <= 0) {
            return 0;
        }

        $passingScore = PassingRateSetting::passingScore($maxScore, $yearLevel);

        return round(($scores->filter(fn (float $score): bool => $score >= $passingScore)->count() / $scores->count()) * 100, 2);
    }

    private function submissionScore(Submission $submission, Collection $items): float
    {
        return AssessmentScoring::scoreSubmission($submission, $items);
    }
}
