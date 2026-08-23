<?php

namespace App\Http\Controllers\Instructor;

use App\Models\ClassAssessment;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Support\AssessmentScoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentResultController extends BaseController
{
    public function assessmentResults(ClassAssessment $classAssessment): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);
        $ownedClassAssessment = $this->ownedClassAssessment($classAssessment, $instructorProfile);
        $isCompleted = $ownedClassAssessment->publish_status === ClassAssessment::STATUS_CLOSED
            || ($ownedClassAssessment->due_at && $ownedClassAssessment->due_at->isPast());

        abort_unless($isCompleted, 404, 'Results are available after the assessment is completed.');

        $ownedClassAssessment->load([
            'assessment.subject',
            'assessment.items.choices',
            'class.subject',
            'class.students.user',
            'submissions' => fn ($query) => $query
                ->with(['studentProfile.user', 'answers.choice', 'securityEvents'])
                ->orderBy('attempt_number'),
        ]);

        $items = $ownedClassAssessment->assessment?->items ?? collect();
        $maxScore = (float) $items->sum(fn ($item) => (float) $item->points);
        $passingScore = $maxScore * 0.75;
        $submissions = $ownedClassAssessment->submissions;
        $students = $ownedClassAssessment->class?->students ?? collect();
        $submissionStudents = $submissions
            ->pluck('studentProfile')
            ->filter();

        $studentResults = $students
            ->concat($submissionStudents)
            ->unique('student_profile_id')
            ->sortBy(fn (StudentProfile $student): string => Str::lower($student->user?->displayName() ?? $student->student_number ?? ''))
            ->map(function (StudentProfile $student) use ($submissions, $items, $maxScore, $passingScore): array {
                $attempts = $submissions
                    ->where('student_profile_id', $student->student_profile_id)
                    ->map(function (Submission $submission) use ($items, $maxScore, $passingScore): array {
                        $isSubmitted = $submission->status === Submission::STATUS_SUBMITTED;
                        $score = $isSubmitted ? $this->submissionScore($submission, $items) : null;

                        return [
                            'submission_id' => $submission->submission_id,
                            'status' => $submission->status,
                            'score' => $score !== null ? $this->formatReportNumber($score) : null,
                            'score_value' => $score,
                            'passed' => $score !== null && $maxScore > 0 && $score >= $passingScore,
                            'submitted_at' => $submission->submitted_at,
                            'warning_count' => $submission->warning_count,
                            'completion_reason' => $submission->completion_reason,
                            'pending_essay_count' => AssessmentScoring::essayPendingCount($submission, $items),
                            'security_events' => $submission->securityEvents
                                ->map(fn ($event): array => [
                                    'label' => $event->displayLabel(),
                                    'occurred_at' => $event->occurred_at,
                                ])
                                ->values(),
                        ];
                    })
                    ->values();
                $bestAttempt = $attempts
                    ->where('status', Submission::STATUS_SUBMITTED)
                    ->sortByDesc('score_value')
                    ->first();
                $submittedAttemptsCount = $attempts
                    ->where('status', Submission::STATUS_SUBMITTED)
                    ->count();
                $latestSubmittedAttempt = $attempts
                    ->where('status', Submission::STATUS_SUBMITTED)
                    ->sortByDesc(fn (array $attempt): int => $attempt['submitted_at']?->timestamp ?? 0)
                    ->first();

                return [
                    'student' => $student,
                    'attempts' => $attempts,
                    'best_attempt' => $bestAttempt,
                    'latest_submitted_attempt' => $latestSubmittedAttempt,
                    'submitted_attempts_count' => $submittedAttemptsCount,
                ];
            })
            ->values();
        $analytics = $this->classAssessmentReportAnalytics($ownedClassAssessment);
        $autoSubmittedCount = $submissions
            ->where('status', Submission::STATUS_SUBMITTED)
            ->where('completion_reason', Submission::COMPLETION_WARNING_LIMIT)
            ->count();

        Log::info('Instructor viewed completed assessment results.', [
            'actor_id' => $user->id,
            'class_assessment_id' => $ownedClassAssessment->class_assessment_id,
            'assessment_id' => $ownedClassAssessment->assessment_id,
            'class_id' => $ownedClassAssessment->class_id,
        ]);

        return view('instructor.assessments.results', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedClassAssessment->assessment,
            'class' => $ownedClassAssessment->class,
            'studentResults' => $studentResults,
            'analytics' => $analytics,
            'autoSubmittedCount' => $autoSubmittedCount,
        ]);
    }

    public function gradeSubmission(Submission $submission): View
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        $submission->load([
            'studentProfile.user',
            'classAssessment.assessment.items.choices',
            'classAssessment.class.subject',
            'answers.choice',
            'answers.item.choices',
            'answers.checker',
        ]);

        $ownedClassAssessment = $this->ownedClassAssessment($submission->classAssessment, $instructorProfile);

        abort_unless($submission->status === Submission::STATUS_SUBMITTED, 404, 'Only submitted attempts can be checked.');

        $items = $ownedClassAssessment->assessment?->items ?? collect();
        $answers = $submission->answers->keyBy('assessment_item_id');
        $rows = $items->map(function ($item) use ($answers): array {
            $answer = $answers->get($item->assessment_item_id);

            return [
                'item' => $item,
                'answer' => $answer,
                'earned_points' => AssessmentScoring::earnedPoints($item, $answer),
                'is_correct' => AssessmentScoring::isCorrect($item, $answer),
            ];
        });
        $maxScore = (float) $items->sum(fn ($item): float => (float) $item->points);
        $score = AssessmentScoring::scoreSubmission($submission, $items);

        return view('instructor.assessments.grade-submission', $this->sharedData($user, 'assessments') + [
            'submission' => $submission,
            'classAssessment' => $ownedClassAssessment,
            'assessment' => $ownedClassAssessment->assessment,
            'class' => $ownedClassAssessment->class,
            'student' => $submission->studentProfile,
            'rows' => $rows,
            'scoreText' => $this->formatReportNumber($score),
            'maxScoreText' => $this->formatReportNumber($maxScore),
            'pendingEssayCount' => AssessmentScoring::essayPendingCount($submission, $items),
        ]);
    }

    public function updateSubmissionGrade(Request $request, Submission $submission): RedirectResponse
    {
        $user = $this->currentUser();
        $instructorProfile = $this->instructorProfile($user);

        $submission->load([
            'classAssessment.assessment.items',
            'answers.item',
        ]);

        $ownedClassAssessment = $this->ownedClassAssessment($submission->classAssessment, $instructorProfile);

        abort_unless($submission->status === Submission::STATUS_SUBMITTED, 404, 'Only submitted attempts can be checked.');

        $validated = $request->validate([
            'essay_scores' => ['nullable', 'array'],
            'essay_scores.*' => ['nullable', 'numeric', 'min:0'],
            'essay_feedback' => ['nullable', 'array'],
            'essay_feedback.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $scores = collect($validated['essay_scores'] ?? []);
        $feedback = collect($validated['essay_feedback'] ?? []);
        $essayAnswers = $submission->answers
            ->filter(fn ($answer): bool => $answer->item?->item_type === 'essay')
            ->keyBy('submission_answer_id');
        $errors = [];

        foreach ($essayAnswers as $answerId => $answer) {
            $rawScore = $scores->get((string) $answerId);
            $maxPoints = (float) $answer->item->points;

            if ($rawScore === null || $rawScore === '') {
                $errors["essay_scores.{$answerId}"] = 'Please enter a score for this essay answer.';
                continue;
            }

            $score = (float) $rawScore;

            if ($score > $maxPoints) {
                $errors["essay_scores.{$answerId}"] = 'The score cannot exceed '.$this->formatReportNumber($maxPoints).' points.';
                continue;
            }

            $answer->update([
                'earned_points' => AssessmentScoring::clampPoints($score, $maxPoints),
                'feedback' => $feedback->get((string) $answerId),
                'checked_by' => $user->id,
                'checked_at' => now(),
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        Log::info('Instructor manually checked essay answers.', [
            'actor_id' => $user->id,
            'submission_id' => $submission->submission_id,
            'class_assessment_id' => $ownedClassAssessment->class_assessment_id,
        ]);

        return redirect()
            ->route('instructor.assessments.submissions.grade', $submission)
            ->with('status', 'Essay scores saved.');
    }
}
