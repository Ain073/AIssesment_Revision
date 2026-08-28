<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicIdSeeder extends Seeder
{
    private array $tables = [
        'users' => 'id',
        'colleges' => 'college_id',
        'departments' => 'department_id',
        'programs' => 'program_id',
        'subject_program' => 'subject_program_id',
        'classes' => 'class_id',
        'assessments' => 'assessment_id',
        'publish_assessment' => 'publish_assessment_id',
        'student_profiles' => 'student_profile_id',
        'class_join_requests' => 'class_join_request_id',
        'app_notifications' => 'notification_id',
    ];

    public function run(): void
    {
        foreach ($this->tables as $tableName => $primaryKey) {
            $this->fillMissingPublicIds($tableName, $primaryKey);
        }
    }

    private function fillMissingPublicIds(string $tableName, string $primaryKey): void
    {
        DB::table($tableName)
            ->whereNull('public_id')
            ->orderBy($primaryKey)
            ->get([$primaryKey])
            ->each(function ($row) use ($tableName, $primaryKey): void {
                DB::table($tableName)
                    ->where($primaryKey, $row->{$primaryKey})
                    ->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
            });
    }
}
