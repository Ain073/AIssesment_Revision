<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
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

    public function up(): void
    {
        foreach ($this->tables as $tableName => $primaryKey) {
            $this->addPublicIdColumn($tableName);
            $this->fillMissingPublicIds($tableName, $primaryKey);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            if (! Schema::hasColumn($tableName, 'public_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }

    private function addPublicIdColumn(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'public_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
        });
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
                    ->update(['public_id' => (string) Str::uuid()]);
            });
    }
};
