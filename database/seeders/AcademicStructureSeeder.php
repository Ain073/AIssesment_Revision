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

        Department::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'dept_name' => 'Department of Information Technology',
            ],
            [],
        );

        Department::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'dept_name' => 'Hospitality Management Department',
            ],
            [],
        );

        Program::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'program_name' => 'Bachelor of Science in Information Technology',
            ],
            [
                'is_active' => true,
            ],
        );

        Program::query()->updateOrCreate(
            [
                'college_id' => $college->college_id,
                'program_name' => 'Bachelor of Science in Hospitality Management',
            ],
            [
                'is_active' => true,
            ],
        );
    }
}
