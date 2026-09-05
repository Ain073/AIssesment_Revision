<?php

namespace App\Http\Controllers\DepartmentChair;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DepartmentChair\Helpers\DepartmentChairLayoutHelper;
use App\Models\Department;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccountImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

abstract class BaseController extends Controller
{
    use DepartmentChairLayoutHelper;

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'instructorProfile.department.college');

        return $user;
    }

    protected function scopedDepartment(User $user): ?Department
    {
        return $user->instructorProfile?->department;
    }

    /**
     * @return Collection<int, Program>
     */
    protected function scopedPrograms(?Department $department): Collection
    {
        if (! $department) {
            return collect();
        }

        return Program::query()
            ->with('department.college')
            ->where('department_id', $department->department_id)
            ->withCount('studentProfiles')
            ->orderBy('program_name')
            ->get();
    }

    protected function studentImportScope(?Department $department): string
    {
        return 'department:'.(int) $department?->department_id;
    }

    protected function departmentUserDirectoryData(
        User $user,
        Request $request,
        StudentAccountImportService $importer,
        string $activeUserTab = 'teachers'
    ): array {
        $scopedDepartment = $this->scopedDepartment($user);
        $scopedPrograms = $this->scopedPrograms($scopedDepartment);
        $scopedProgramIds = $scopedPrograms->pluck('program_id');
        $scopedDepartmentId = $scopedDepartment?->department_id;

        $teachers = User::with(['roles', 'instructorProfile.department.college'])
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'instructor'))
            ->when(
                $scopedDepartmentId,
                fn ($query) => $query->whereHas('instructorProfile', fn ($inner) => $inner->where('department_id', $scopedDepartmentId)),
                fn ($query) => $query->where('id', 0)
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get();

        $students = $scopedProgramIds->isNotEmpty()
            ? StudentProfile::query()
                ->with(['user.roles', 'program.department.college'])
                ->whereIn('program_id', $scopedProgramIds)
                ->get()
                ->sortBy(fn (StudentProfile $student) => strtolower($student->user?->displayName() ?? ''))
                ->values()
            : collect();

        return [
            'teachers' => $teachers,
            'students' => $students,
            'teachersCount' => $teachers->count(),
            'studentsCount' => $students->count(),
            'scopedDepartment' => $scopedDepartment,
            'scopedPrograms' => $scopedPrograms,
            'activeUserTab' => $activeUserTab === 'students' ? 'students' : 'teachers',
            'studentImportPreview' => $importer->previewForRequest($request, $this->studentImportScope($scopedDepartment)),
        ];
    }

}
