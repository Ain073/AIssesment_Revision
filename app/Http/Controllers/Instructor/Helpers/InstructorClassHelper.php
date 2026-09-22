<?php

namespace App\Http\Controllers\Instructor\Helpers;

use App\Models\AcademicClass;
use App\Models\InstructorProfile;
use App\Models\Program;
use App\Models\Semester;
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
                ->with(['contextDetail', 'subject', 'program', 'semester'])
            : null;
        $activeClasses = $baseClassesQuery
            ? (clone $baseClassesQuery)
                ->whereNull('classes.archived_at')
                ->latest('classes.class_id')
                ->get()
                ->each(fn (AcademicClass $class) => $class->applyEnrolledStudentsCount())
            : collect();
        $archivedClasses = $baseClassesQuery
            ? (clone $baseClassesQuery)
                ->whereNotNull('classes.archived_at')
                ->latest('classes.archived_at')
                ->get()
                ->each(fn (AcademicClass $class) => $class->applyEnrolledStudentsCount())
            : collect();
        $classes = $activeClassTab === 'archived' ? $archivedClasses : $activeClasses;
        $activeClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNull('classes.archived_at')->count()
            : 0;
        $archivedClassesCount = $baseClassesQuery
            ? (clone $baseClassesQuery)->whereNotNull('classes.archived_at')->count()
            : 0;
        $activeSubjectIds = $this->activeSubjectIds($instructorProfile);
        $existingSubjectIds = $activeClasses->merge($archivedClasses)->pluck('subject_id')->filter();
        $activeSubjects = Subject::query()
            ->with(['program'])
            ->whereIn('subject_id', $activeSubjectIds)
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
        $subjects = Subject::query()
            ->with(['program'])
            ->whereIn('subject_id', $activeSubjectIds->merge($existingSubjectIds)->unique())
            ->orderBy('subject_code')
            ->orderBy('subject_name')
            ->get();
        $activePrograms = $this->activeClassPrograms($instructorProfile);
        $programs = Program::query()
            ->with('department.college')
            ->whereIn('program_id', $activePrograms->pluck('program_id')
                ->merge($activeClasses->merge($archivedClasses)->pluck('program_id')->filter())
                ->unique())
            ->orderBy('program_name')
            ->get();
        $semesters = Semester::query()
            ->orderBy('semester_id')
            ->get();
        $activeSemester = Semester::query()
            ->where('is_active', true)
            ->first(['semester_id', 'semester_name']);

        return [
            'instructorProfile' => $instructorProfile,
            'classes' => $classes,
            'activeClasses' => $activeClasses,
            'archivedClasses' => $archivedClasses,
            'subjects' => $subjects,
            'activeSubjects' => $activeSubjects,
            'programs' => $programs,
            'activePrograms' => $activePrograms,
            'semesters' => $semesters,
            'activeSemesterId' => $activeSemester?->semester_id,
            'activeSemesterName' => $activeSemester?->semester_name,
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

    protected function activeClassPrograms(?InstructorProfile $instructorProfile): \Illuminate\Support\Collection
    {
        if (! $instructorProfile?->department_id) {
            return collect();
        }

        return Program::query()
            ->with('department.college')
            ->where('department_id', $instructorProfile->department_id)
            ->where('is_active', true)
            ->orderBy('program_name')
            ->get();
    }
}
