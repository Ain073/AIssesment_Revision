<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('warning_count');
            $table->timestamp('last_activity_at')->nullable()->after('started_at');
            $table->string('completion_reason', 40)->nullable()->after('status');
            $table->index(['status', 'last_activity_at'], 'submissions_status_activity_index');
        });

        DB::table('submissions')
            ->whereNull('started_at')
            ->update([
                'started_at' => DB::raw('COALESCE(submitted_at, created_at)'),
                'last_activity_at' => DB::raw('COALESCE(submitted_at, updated_at, created_at)'),
                'completion_reason' => 'manual_submit',
            ]);

        Schema::create('submission_security_events', function (Blueprint $table) {
            $table->id('submission_security_event_id');
            $table->foreignId('submission_id')
                ->constrained('submissions', 'submission_id')
                ->cascadeOnDelete();
            $table->string('event_uuid', 64)->unique();
            $table->string('event_type', 50);
            $table->timestamp('occurred_at');
            $table->char('request_fingerprint', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'occurred_at'], 'submission_security_event_timeline_index');
            $table->index(['event_type', 'occurred_at'], 'submission_security_event_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_security_events');

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_status_activity_index');
            $table->dropColumn([
                'started_at',
                'last_activity_at',
                'completion_reason',
            ]);
        });
    }
};
