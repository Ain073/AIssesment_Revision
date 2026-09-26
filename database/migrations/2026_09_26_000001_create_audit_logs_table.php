<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('audit_log_id');

            // Who performed the action (nullable: system-generated or deleted user)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Role snapshot at the time of action (denormalized for historical accuracy)
            $table->string('user_role', 50)->nullable(); // 'super_admin', 'admin_dean', 'department_chair', 'instructor', 'student'

            // What was done
            $table->string('action', 50);   // 'CREATE', 'UPDATE', 'DELETE', 'PUBLISH', 'GRADE', 'LOGIN', 'LOGOUT', 'DESIGNATE', 'REVOKE', 'ARCHIVE', 'APPROVE'
            $table->string('module', 80);   // 'Assessments', 'Classes', 'Grading', 'Users', 'Designations', 'Auth', 'Reports', 'Subjects', 'Programs'

            // Human-readable description
            $table->text('description');

            // Optional: which specific model/record was involved
            $table->nullableMorphs('auditable'); // auditable_type, auditable_id

            // Client/network info for security audit
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // No updated_at — audit logs are immutable (append-only)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
