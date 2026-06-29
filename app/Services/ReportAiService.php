<?php

namespace App\Services;

use App\Models\ClassAssessment;
use App\Models\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportAiService
{
    public function generate(ClassAssessment $classAssessment): array
    {
        $data = $this->reportData($classAssessment);
        $provider = (string) config('services.ai_report.provider', 'mock');
        $model = (string) config('services.ai_report.model', '');
        $key = (string) config('services.ai_report.key', '');

        if ($provider === 'mock' || $model === '' || $key === '') {
            return $this->mockDraft($data, 'mock');
        }

        try {
            if ($provider === 'openai') {
                return $this->openAiDraft($data, $model, $key);
            }

            if ($provider === 'claude') {
                return $this->claudeDraft($data, $model, $key);
            }

            return $this->mockDraft($data, 'mock');
        } catch (\Throwable $exception) {
            Log::warning('AI report draft failed. Mock draft was used instead.', [
                'provider' => $provider,
                'class_assessment_id' => $classAssessment->class_assessment_id,
                'message' => $exception->getMessage(),
            ]);

            return $this->mockDraft($data, 'mock');
        }
    }

    private function openAiDraft(array $data, string $model, string $key): array
    {
        $response = Http::withToken($key)
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => $this->prompt($data),
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

                return $answer ? $this->isCorrect($item, $answer) : false;
            })->count();
            $total = $submissions->count();

            return [
                'question' => Str::limit((string) $item->question_text, 120),
                'correct_rate' => $total > 0 ? round(($correctCount / $total) * 100, 2) : 0,
            ];
        })->values()->all();
    }

    private function prompt(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are helping an instructor prepare a school performance monitoring report.

Use only the assessment data below. Do not invent scores, student names, or unsupported facts.
Write concise academic report content for these two fields only:
1. concepts_most_learned_skills
2. concepts_least_learned_skills

Return valid JSON only with these exact keys:
{
  "concepts_most_learned_skills": "...",
  "concepts_least_learned_skills": "..."
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

    private function mockDraft(array $data, string $source): array
    {
        $items = collect($data['items']);
        $strongItems = $items->sortByDesc('correct_rate')->take(2)->pluck('question')->filter()->values();
        $weakItems = $items->sortBy('correct_rate')->take(2)->pluck('question')->filter()->values();
        $subject = $data['subject'] !== '' ? $data['subject'] : $data['assessment_title'];

        return [
            'concepts_most_learned_skills' => $strongItems->isNotEmpty()
                ? 'Students showed stronger understanding of '.$subject.', especially in items related to '.$strongItems->implode('; ').'.'
                : 'Students showed stronger understanding of the assessed topics based on the submitted assessment results.',
            'concepts_least_learned_skills' => $weakItems->isNotEmpty()
                ? 'Students need more guided practice in '.$subject.', especially in items related to '.$weakItems->implode('; ').'.'
                : 'Students need more guided practice in the lower-performing parts of the assessment.',
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
        return (float) $items->sum(function ($item) use ($submission): float {
            $answer = $submission->answers->firstWhere('assessment_item_id', $item->assessment_item_id);

            return $answer && $this->isCorrect($item, $answer) ? (float) $item->points : 0.0;
        });
    }

    private function isCorrect($item, $answer): bool
    {
        if ($answer->choice) {
            return (bool) $answer->choice->is_correct;
        }

        $correctAnswers = $item->choices
            ->where('is_correct', true)
            ->pluck('choice_text')
            ->map(fn ($choice): string => Str::lower(trim((string) $choice)))
            ->filter();
        $studentAnswer = Str::lower(trim((string) $answer->answer_text));

        return $studentAnswer !== '' && $correctAnswers->contains($studentAnswer);
    }
}
