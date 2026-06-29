<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicSetting;
use App\Models\Assessment;
use App\Models\ClassAssessment;
use App\Models\InstructorProfile;
use App\Models\Subject;
use App\Models\SubjectProgram;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait InstructorAssessmentHelper
{
    protected function publishAssessmentToClasses(
        Request $request,
        User $user,
        ?InstructorProfile $instructorProfile,
        Assessment $ownedAssessment,
        array $validated
    ): void {
        $classIds = collect($validated['class_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $ownedClasses = $instructorProfile
            ? $instructorProfile->classes()
                ->where('subject_id', $ownedAssessment->subject_id)
                ->whereNull('archived_at')
                ->whereIn('class_id', $classIds)
                ->get()
            : collect();

        if ($ownedClasses->count() !== $classIds->count()) {
            throw ValidationException::withMessages([
                'class_ids' => 'You can only publish to your active classes under the same subject.',
            ]);
        }

        DB::transaction(function () use ($ownedAssessment, $ownedClasses, $validated, $request) {
            foreach ($ownedClasses as $class) {
                ClassAssessment::query()->updateOrCreate(
                    [
                        'assessment_id' => $ownedAssessment->assessment_id,
                        'class_id' => $class->class_id,
                    ],
                    [
                        'available_at' => $validated['available_at'] ?? null,
                        'due_at' => $validated['due_at'] ?? null,
                        'publish_status' => ClassAssessment::STATUS_PUBLISHED,
                        'score_visibility' => $request->boolean('score_visibility'),
                        'answer_visibility' => $request->boolean('answer_visibility'),
                        'prevent_copy_paste' => $request->boolean('prevent_copy_paste'),
                        'detect_tab_switch' => $request->boolean('detect_tab_switch'),
                        'screenshot_protection' => $request->boolean('screenshot_protection'),
                        'attempt_limit' => (int) $validated['attempt_limit'],
                        'shuffle_items' => $request->boolean('shuffle_items'),
                        'shuffle_choices' => $request->boolean('shuffle_choices'),
                        'warning_limit' => $validated['warning_limit'] ?? 3,
                        'display_mode' => $validated['display_mode'],
                    ],
                );
            }

            $ownedAssessment->update(['status' => Assessment::STATUS_READY]);
        });

        Log::info('Assessment published to classes by instructor.', [
            'actor_id' => $user->id,
            'assessment_id' => $ownedAssessment->assessment_id,
            'class_ids' => $ownedClasses->pluck('class_id')->all(),
        ]);

        $students = $ownedClasses
            ->load('students.user')
            ->pluck('students')
            ->flatten()
            ->pluck('user')
            ->filter();

        app(NotificationService::class)->sendToMany(
            $students,
            'New Assessment',
            $ownedAssessment->title.' was published to your class.',
            route('student.assessments'),
            'assessment'
        );
    }

    protected function assessmentTypes(): array
    {
        return [
            'quiz' => 'Quiz',
            'exam' => 'Exam',
            'activity' => 'Activity',
            'assignment' => 'Assignment',
        ];
    }

    protected function itemTypes(): array
    {
        return [
            'multiple_choice' => 'Multiple Choice',
            'identification' => 'Identification',
            'essay' => 'Essay',
            'true_false' => 'True/False',
        ];
    }

    protected function handledSubjects(?InstructorProfile $instructorProfile): Collection
    {
        if (! $instructorProfile) {
            return collect();
        }

        return Subject::query()
            ->whereIn('subject_id', $instructorProfile->classes()
                ->whereNotNull('subject_id')
                ->whereNull('archived_at')
                ->select('subject_id'))
            ->where('is_active', true)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
    }

    protected function activeSubjectIds(): Collection
    {
        $activeSemester = AcademicSetting::query()->value('active_semester');

        if (! $activeSemester) {
            return collect();
        }

        return SubjectProgram::query()
            ->where('semester', $activeSemester)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->distinct()
            ->pluck('subject_id');
    }

    protected function ownedAssessment(Assessment $assessment, ?InstructorProfile $instructorProfile): Assessment
    {
        abort_unless(
            $instructorProfile && $assessment->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to manage this assessment.'
        );

        return $assessment;
    }

    protected function ownedClassAssessment(ClassAssessment $classAssessment, ?InstructorProfile $instructorProfile): ClassAssessment
    {
        $classAssessment->loadMissing('assessment');

        abort_unless(
            $instructorProfile
                && $classAssessment->assessment
                && $classAssessment->assessment->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access these assessment results.'
        );

        return $classAssessment;
    }
}
