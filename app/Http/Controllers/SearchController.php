<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassAssessment;
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
            ->with('college')
            ->where('program_name', 'like', "%{$query}%")
            ->orderBy('program_name')
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (Program $program): array => [
                'title' => $program->program_name,
                'subtitle' => 'Program - '.($program->college?->college_name ?? 'No college'),
                'type' => 'Program',
                'url' => route('super-admin.programs'),
            ]);

        $subjects = Subject::query()
            ->where(function ($search) use ($query): void {
                $search->where('subject_code', 'like', "%{$query}%")
                    ->orWhere('subject_name', 'like', "%{$query}%");
            })
            ->orderBy('subject_code')
            ->limit(3)
            ->get()
            ->toBase()
            ->map(fn (Subject $subject): array => [
                'title' => $subject->subject_code,
                'subtitle' => $subject->subject_name,
                'type' => 'Subject',
                'url' => route('super-admin.subjects'),
            ]);

        return $users->merge($programs)->merge($subjects);
    }

    private function instructorResults(string $query, User $user)
    {
        $instructorProfile = $user->instructorProfile;

        if (! $instructorProfile) {
            return collect();
        }

        $classes = AcademicClass::query()
            ->with('subject')
            ->where('instructor_id', $instructorProfile->instructor_profile_id)
            ->where(function ($search) use ($query): void {
                $search->where('class_name', 'like', "%{$query}%")
                    ->orWhere('school_year', 'like', "%{$query}%")
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

        $completed = ClassAssessment::query()
            ->with(['assessment.subject', 'class'])
            ->whereHas('class', fn ($classQuery) => $classQuery->where('instructor_id', $instructorProfile->instructor_profile_id))
            ->where(function ($statusQuery): void {
                $statusQuery->where('publish_status', ClassAssessment::STATUS_CLOSED)
                    ->orWhere(function ($dueQuery): void {
                        $dueQuery->whereNotNull('due_at')
                            ->where('due_at', '<=', now());
                    });
            })
            ->where(function ($search) use ($query): void {
                $search->whereHas('assessment', function ($assessmentQuery) use ($query): void {
                    $assessmentQuery->where('title', 'like', "%{$query}%");
                })
                    ->orWhereHas('class', function ($classQuery) use ($query): void {
                        $classQuery->where('class_name', 'like', "%{$query}%");
                    });
            })
            ->latest('class_assessment_id')
            ->limit(2)
            ->get()
            ->toBase()
            ->map(fn (ClassAssessment $classAssessment): array => [
                'title' => $classAssessment->assessment?->title ?? 'Assessment Results',
                'subtitle' => 'Results - '.($classAssessment->class?->class_name ?? 'Class'),
                'type' => 'Result',
                'url' => route('instructor.assessments.results', $classAssessment),
            ]);

        return $classes->merge($assessments)->merge($completed);
    }

    private function studentResults(string $query, User $user)
    {
        $studentProfile = $user->studentProfile;

        if (! $studentProfile) {
            return collect();
        }

        $classes = $studentProfile->classes()
            ->with('subject')
            ->where(function ($search) use ($query): void {
                $search->where('class_name', 'like', "%{$query}%")
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

        $classIds = $studentProfile->classes()->pluck('classes.class_id');
        $assessments = ClassAssessment::query()
            ->with(['assessment.subject', 'class'])
            ->whereIn('class_id', $classIds)
            ->where('publish_status', ClassAssessment::STATUS_PUBLISHED)
            ->whereHas('assessment', function ($assessmentQuery) use ($query): void {
                $assessmentQuery->where('title', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (ClassAssessment $classAssessment): array => [
                'title' => $classAssessment->assessment?->title ?? 'Assessment',
                'subtitle' => 'Assessment - '.($classAssessment->class?->class_name ?? 'Class'),
                'type' => 'Assessment',
                'url' => route('student.assessments.take', $classAssessment),
            ]);

        return $classes->merge($assessments);
    }
}
