<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\PublishAssessment;
use App\Models\ClassDetail;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user()->loadMissing('roles', 'instructorProfile', 'studentProfile');
        $results = collect();

        if ($user->hasRole('super_admin')) {
            $results = $results->merge($this->superAdminResults($query));
        }

        if ($user->roles->pluck('role_name')->intersect(['instructor', 'admin_dean', 'department_chair'])->isNotEmpty()) {
            $results = $results->merge($this->instructorResults($query, $user));
        }

        if ($user->hasRole('department_chair')) {
            $results = $results->merge($this->departmentChairResults($query, $user));
        }

        if ($user->hasRole('student')) {
            $results = $results->merge($this->studentResults($query, $user));
        }

        return response()->json([
            'results' => $results
                ->take(8)
                ->values(),
        ]);
    }

    private function superAdminResults(string $query)
    {
        $users = User::query()
            ->where(function ($search) use ($query): void {
                $search->where('name', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('last_name')
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (User $user): array => [
                'title' => $user->displayName(),
                'subtitle' => 'User account - '.$user->email,
                'type' => 'User',
                'url' => route('super-admin.users'),
            ]);

        $programs = Program::query()
            ->with('department.college')
            ->where('program_name', 'like', "%{$query}%")
            ->orderBy('program_name')
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (Program $program): array => [
                'title' => $program->program_name,
                'subtitle' => 'Program - '.(collect([$program->department?->dept_name, $program->department?->college?->college_name])->filter()->join(' - ') ?: 'No department'),
                'type' => 'Program',
                'url' => route('super-admin.programs'),
            ]);

        return $users->merge($programs);
    }

    private function departmentChairResults(string $query, User $user)
    {
        $departmentId = $user->instructorProfile?->department_id;

        if (! $departmentId) {
            return collect();
        }

        return Subject::query()
            ->with(['semester', 'program'])
            ->whereHas('program', fn ($programQuery) => $programQuery->where('department_id', $departmentId))
            ->where(function ($search) use ($query): void {
                $search->where('subject_code', 'like', "%{$query}%")
                    ->orWhere('subject_name', 'like', "%{$query}%")
                    ->orWhereHas('program', fn ($programQuery) => $programQuery->where('program_name', 'like', "%{$query}%"));
            })
            ->orderBy('year_level')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (Subject $subject): array => [
                'title' => $subject->subject_code,
                'subtitle' => trim($subject->subject_name.' - '.($subject->program?->program_name ?? 'Program'), ' -'),
                'type' => 'Subject',
                'url' => route('department-chair.subjects', array_filter([
                    'program' => $subject->program?->public_id,
                    'year_level' => $subject->year_level,
                    'semester' => $subject->semester?->semester_name,
                ])),
            ]);
    }

    private function instructorResults(string $query, User $user)
    {
        $instructorProfile = $user->instructorProfile;

        if (! $instructorProfile) {
            return collect();
        }

        $classes = AcademicClass::query()
            ->with(['contextDetail', 'subject'])
            ->whereHas('contextDetail', fn ($detailQuery) => $detailQuery
                ->where('instructor_id', $instructorProfile->instructor_profile_id))
            ->where(function ($search) use ($query): void {
                $search->where('section_name', 'like', "%{$query}%")
                    ->orWhere('join_code', 'like', "%{$query}%")
                    ->orWhereHas('subject', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('subject_code', 'like', "%{$query}%")
                            ->orWhere('subject_name', 'like', "%{$query}%");
                    });
            })
            ->latest('class_id')
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (AcademicClass $class): array => [
                'title' => $class->class_name,
                'subtitle' => 'Class - '.($class->subject?->subject_code ?? 'No subject'),
                'type' => 'Class',
                'url' => route('instructor.classes.show', $class),
            ]);

        $assessments = Assessment::query()
            ->with('subject')
            ->where('instructor_id', $instructorProfile->instructor_profile_id)
            ->where(function ($search) use ($query): void {
                $search->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhereHas('subject', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('subject_code', 'like', "%{$query}%")
                            ->orWhere('subject_name', 'like', "%{$query}%");
                    });
            })
            ->latest('assessment_id')
            ->limit(4)
            ->get()
            ->toBase()
            ->map(fn (Assessment $assessment): array => [
                'title' => $assessment->title,
                'subtitle' => 'Assessment - '.($assessment->subject?->subject_code ?? 'No subject'),
                'type' => 'Assessment',
                'url' => route('instructor.assessments.show', $assessment),
            ]);

        $completed = PublishAssessment::query()
            ->with(['assessment.subject', 'classDetail.class'])
            ->whereHas('classDetail', fn ($detailQuery) => $detailQuery
                ->where('instructor_id', $instructorProfile->instructor_profile_id))
            ->where(function ($statusQuery): void {
                $statusQuery->where('publish_status', PublishAssessment::STATUS_CLOSED)
                    ->orWhere(function ($dueQuery): void {
                        $dueQuery->whereNotNull('due_at')
                            ->where('due_at', '<=', now());
                    });
            })
            ->where(function ($search) use ($query): void {
                $search->whereHas('assessment', function ($assessmentQuery) use ($query): void {
                    $assessmentQuery->where('title', 'like', "%{$query}%");
                })
                    ->orWhereHas('classDetail.class', function ($classQuery) use ($query): void {
                        $classQuery->where('section_name', 'like', "%{$query}%")
                            ->orWhere('join_code', 'like', "%{$query}%");
                    });
            })
            ->latest('publish_assessment_id')
            ->limit(2)
            ->get()
            ->toBase()
            ->map(fn (PublishAssessment $publishAssessment): array => [
                'title' => $publishAssessment->assessment?->title ?? 'Assessment Results',
                'subtitle' => 'Results - '.($publishAssessment->class?->class_name ?? 'Class'),
                'type' => 'Result',
                'url' => route('instructor.assessments.results', $publishAssessment),
            ]);

        return $classes->merge($assessments)->merge($completed);
    }

    private function studentResults(string $query, User $user)
    {
        $studentProfile = $user->studentProfile;

        if (! $studentProfile) {
            return collect();
        }

        $classes = $this->classesForStudent($studentProfile)
            ->with('subject')
            ->where(function ($search) use ($query): void {
                $search->where('section_name', 'like', "%{$query}%")
                    ->orWhere('join_code', 'like', "%{$query}%")
                    ->orWhereHas('subject', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('subject_code', 'like', "%{$query}%")
                            ->orWhere('subject_name', 'like', "%{$query}%");
                    });
            })
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (AcademicClass $class): array => [
                'title' => $class->class_name,
                'subtitle' => 'Class - '.($class->subject?->subject_code ?? 'No subject'),
                'type' => 'Class',
                'url' => route('student.classes'),
            ]);

        $classIds = $this->classesForStudent($studentProfile)->pluck('classes.class_id');
        $assessments = PublishAssessment::query()
            ->with(['assessment.subject', 'classDetail.class'])
            ->whereHas('classDetail', fn ($detailQuery) => $detailQuery->whereIn('class_id', $classIds))
            ->where('publish_status', PublishAssessment::STATUS_PUBLISHED)
            ->whereHas('assessment', function ($assessmentQuery) use ($query): void {
                $assessmentQuery->where('title', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (PublishAssessment $publishAssessment): array => [
                'title' => $publishAssessment->assessment?->title ?? 'Assessment',
                'subtitle' => 'Assessment - '.($publishAssessment->class?->class_name ?? 'Class'),
                'type' => 'Assessment',
                'url' => route('student.assessments.take', $publishAssessment),
            ]);

        return $classes->merge($assessments);
    }

    private function classesForStudent($studentProfile)
    {
        return AcademicClass::query()
            ->where(function ($query) use ($studentProfile): void {
                $query->whereHas('classDetails', fn ($detailQuery) => $detailQuery
                    ->where('student_id', $studentProfile->student_profile_id)
                    ->where('status', ClassDetail::STATUS_APPROVED));
            });
    }
}
