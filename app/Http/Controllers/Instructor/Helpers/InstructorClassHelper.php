<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicClass;
use App\Models\InstructorProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait InstructorClassHelper
{
    protected function instructorClassesData(User $user, string $activeClassTab = 'active'): array
    {
        $instructorProfile = $this->instructorProfile($user);
        $baseClassesQuery = $instructorProfile
            ? $instructorProfile->classes()
                ->with(['contextDetail', 'subject'])
            : null;
        $classes = $baseClassesQuery
            ? (clone $baseClassesQuery)
                ->when(
                    $activeClassTab === 'archived',
                    fn ($query) => $query->whereNotNull('classes.archived_at'),
                    fn ($query) => $query->whereNull('classes.archived_at')
                )
                ->latest($activeClassTab === 'archived' ? 'classes.archived_at' : 'classes.class_id')
                ->get()
                ->each(fn (AcademicClass $class) => $class->applyEnrolledStudentsCount())
            : collect();
        $activeClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNull('classes.archived_at')->count()
            : 0;
        $archivedClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNotNull('classes.archived_at')->count()
            : 0;
        $activeSubjectIds = $this->activeSubjectIds();
        $existingSubjectIds = $classes->pluck('subject_id')->filter();
        $activeSubjects = Subject::query()
            ->whereIn('subject_id', $activeSubjectIds)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
        $subjects = Subject::query()
            ->whereIn('subject_id', $activeSubjectIds->merge($existingSubjectIds)->unique())
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();

        return [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'subjects' => $subjects,
            'activeSubjects' => $activeSubjects,
            'activeClassTab' => $activeClassTab,
            'activeClassesCount' => $activeClassesCount,
            'archivedClassesCount' => $archivedClassesCount,
            'profileName' => $user->displayName(),
        ];
    }

    protected function classListTab(Request $request): string
    {
        return $request->query('tab') === 'archived' ? 'archived' : 'active';
    }

    protected function classTabs(AcademicClass $class, string $activeTab): array
    {
        return [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'overview']),
                'active' => $activeTab === 'overview',
            ],
            [
                'key' => 'students',
                'label' => 'Students',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'students']),
                'active' => $activeTab === 'students',
            ],
            [
                'key' => 'assessments',
                'label' => 'Assessments',
                'href' => route('instructor.classes.show', ['class' => $class, 'tab' => 'assessments']),
                'active' => $activeTab === 'assessments',
            ],
        ];
    }

    protected function ownedClass(AcademicClass $class, ?InstructorProfile $instructorProfile): AcademicClass
    {
        $class->loadMissing('contextDetail');

        abort_unless(
            $instructorProfile && $class->instructor_id === $instructorProfile->instructor_profile_id,
            403,
            'You are not allowed to access this class.'
        );

        return $class;
    }

    protected function ensureActiveClass(AcademicClass $class): void
    {
        if (! $class->archived_at) {
            return;
        }

        throw ValidationException::withMessages([
            'class' => 'Archived classes are records only. Restore the class before making changes.',
        ]);
    }
}
