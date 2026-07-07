<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSetting;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalUsers = User::count();
        $activeUsers = User::query()->where('status', 'active')->count();
        $inactiveUsers = max($totalUsers - $activeUsers, 0);
        $activeSemester = AcademicSetting::query()->value('active_semester');
        $programStudentRows = Program::query()
            ->with('college')
            ->withCount('studentProfiles')
            ->orderByDesc('student_profiles_count')
            ->orderBy('program_name')
            ->take(6)
            ->get();
        $topProgramStudentCount = (int) $programStudentRows->max('student_profiles_count');

        return view('super-admin.dashboard', [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'activeUserPercentage' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0,
            'inactiveUserPercentage' => $totalUsers > 0 ? round(($inactiveUsers / $totalUsers) * 100) : 0,
            'totalStudents' => $this->countUsersByRole('student'),
            'totalTeachers' => $this->countUsersByRole('instructor'),
            'totalColleges' => College::count(),
            'totalDepartments' => Department::count(),
            'totalAdminDeans' => $this->countUsersByRole('admin_dean'),
            'totalDepartmentChairs' => $this->countUsersByRole('department_chair'),
            'totalPrograms' => Program::count(),
            'activePrograms' => Program::query()->where('is_active', true)->count(),
            'totalSubjects' => Subject::count(),
            'activeSubjects' => Subject::query()->where('is_active', true)->count(),
            'activeSemester' => $activeSemester,
            'activeSemesterSubjectCount' => $activeSemester
                ? SubjectProgram::query()
                    ->where('semester', $activeSemester)
                    ->whereHas('subject', fn ($query) => $query->where('is_active', true))
                    ->distinct('subject_id')
                    ->count('subject_id')
                : 0,
            'programStudentRows' => $programStudentRows->map(function (Program $program) use ($topProgramStudentCount): array {
                $studentCount = (int) $program->student_profiles_count;

                return [
                    'name' => $program->program_name,
                    'college' => $program->college?->college_name ?? 'No college',
                    'students' => $studentCount,
                    'percentage' => $topProgramStudentCount > 0
                        ? max(round(($studentCount / $topProgramStudentCount) * 100), $studentCount > 0 ? 4 : 0)
                        : 0,
                ];
            }),
            'quickActions' => [
                [
                    'label' => 'Users',
                    'href' => route('super-admin.users'),
                    'icon' => 'person_search',
                ],
                [
                    'label' => 'Programs',
                    'href' => route('super-admin.programs'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Subjects',
                    'href' => route('super-admin.subjects'),
                    'icon' => 'menu_book',
                ],
            ],
        ]);
    }

    private function countUsersByRole(string $roleName): int
    {
        return DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('roles.role_name', $roleName)
            ->count();
    }
}
