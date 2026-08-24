<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $college = College::query()->updateOrCreate(
            ['college_name' => 'CHMBAC'],
            [],
        );

        $informationTechnologyDepartment = Department::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'dept_name' => 'Department of Information Technology',
            ],
            [],
        );

        $hospitalityManagementDepartment = Department::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'dept_name' => 'Hospitality Management Department',
            ],
            [],
        );

        Program::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'department_id' => $informationTechnologyDepartment->department_id,
                'program_name' => 'Bachelor of Science in Information Technology',
            ],
            [
                'college_id' => $college->college_id,
                'department_id' => $informationTechnologyDepartment->department_id,
                'is_active' => true,
            ],
        );

        Program::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'department_id' => $hospitalityManagementDepartment->department_id,
                'program_name' => 'Bachelor of Science in Hospitality Management',
            ],
            [
                'college_id' => $college->college_id,
                'department_id' => $hospitalityManagementDepartment->department_id,
                'is_active' => true,
            ],
        );
    }
}
