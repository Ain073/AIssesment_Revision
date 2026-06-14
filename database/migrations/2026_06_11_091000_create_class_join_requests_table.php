<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_join_requests', function (Blueprint $table) {
            $table->id('class_join_request_id');
            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnDelete();
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles', 'student_profile_id')
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'student_profile_id'], 'class_join_request_unique');
            $table->index(['class_id', 'status'], 'class_join_request_class_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_join_requests');
    }
};
