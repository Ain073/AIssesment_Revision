<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_details')
            || ! Schema::hasTable('class_students')
            || ! Schema::hasTable('classes')
            || ! Schema::hasTable('semesters')) {
            return;
        }

        $semesterId = DB::table('semesters')
            ->where('is_active', true)
            ->value('semester_id')
            ?? DB::table('semesters')
                ->orderBy('semester_id')
                ->value('semester_id');

        if (! $semesterId) {
            $semesterId = DB::table('semesters')->insertGetId([
                'semester_name' => 'First Semester',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $now = now();

        DB::table('class_students')
            ->join('classes', 'classes.class_id', '=', 'class_students.class_id')
            ->whereNotNull('classes.instructor_id')
            ->whereNotNull('classes.subject_id')
            ->select([
                'class_students.class_id',
                'class_students.student_profile_id',
                'classes.instructor_id',
                'classes.subject_id',
            ])
            ->orderBy('class_students.class_student_id')
            ->get()
            ->chunk(500)
            ->each(function ($rows) use ($semesterId, $now): void {
                $records = $rows
                    ->map(fn ($row) => [
                        'class_id' => $row->class_id,
                        'instructor_id' => $row->instructor_id,
                        'student_id' => $row->student_profile_id,
                        'subject_id' => $row->subject_id,
                        'semester_id' => $semesterId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($records) {
                    DB::table('class_details')->insertOrIgnore($records);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
