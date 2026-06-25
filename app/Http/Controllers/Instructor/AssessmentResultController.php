<?php

namespace App\Http\Controllers\Instructor;

use App\Models\ClassAssessment;
use App\Models\StudentProfile;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                            'status' => $submission->status,
                            'score' => $score !== null ? $this->formatReportNumber($score) : null,
                            'score_value' => $score,
                            'passed' => $score !== null && $maxScore > 0 && $score >= $passingScore,
                            'submitted_at' => $submission->submitted_at,
                            'warning_count' => $submission->warning_count,
                            'completion_reason' => $submission->completion_reason,
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

                return [
                    'student' => $student,
                    'attempts' => $attempts,
                    'best_attempt' => $bestAttempt,
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

        return view('instructor.assessment-results', $this->sharedData($user, 'assessments') + [
            'assessment' => $ownedClassAssessment->assessment,
            'class' => $ownedClassAssessment->class,
            'studentResults' => $studentResults,
            'analytics' => $analytics,
            'autoSubmittedCount' => $autoSubmittedCount,
        ]);
    }
}
