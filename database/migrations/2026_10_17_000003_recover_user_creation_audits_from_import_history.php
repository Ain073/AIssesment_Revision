<?php

use App\Services\UserCreationAuditRecovery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'metadata')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->json('metadata')->nullable();
            });
        }

        foreach (['users', 'student_profiles', 'roles', 'user_roles'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        app(UserCreationAuditRecovery::class)->recover();
    }

    public function down(): void
    {
        // Keep appended audit events and their provenance on deployment rollback.
    }
};
