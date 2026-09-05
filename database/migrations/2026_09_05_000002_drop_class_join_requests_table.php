<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_join_requests') && Schema::hasTable('class_details')) {
            DB::table('class_join_requests')
                ->join('classes', 'classes.class_id', '=', 'class_join_requests.class_id')
                ->whereNotNull('classes.instructor_id')
                ->whereNotNull('classes.subject_id')
                ->orderBy('class_join_requests.class_join_request_id')
                ->select([
                    'class_join_requests.class_id',
                    'class_join_requests.student_profile_id',
                    'class_join_requests.status',
                    'class_join_requests.created_at',
                    'class_join_requests.updated_at',
                    'classes.instructor_id',
                    'classes.subject_id',
                ])
                ->get()
                ->each(function (object $request): void {
                    DB::table('class_details')->updateOrInsert(
                        [
                            'class_id' => $request->class_id,
                            'student_id' => $request->student_profile_id,
                            'subject_id' => $request->subject_id,
                        ],
                        [
                            'instructor_id' => $request->instructor_id,
                            'status' => $request->status,
                            'entry_method' => 'join_code',
                            'created_at' => $request->created_at ?? now(),
                            'updated_at' => $request->updated_at ?? now(),
                        ],
                    );
                });
        }

        Schema::dropIfExists('class_join_requests');
    }

    public function down(): void
    {
        // The revised class flow stores pending/approved/rejected state in class_details.
    }
};
