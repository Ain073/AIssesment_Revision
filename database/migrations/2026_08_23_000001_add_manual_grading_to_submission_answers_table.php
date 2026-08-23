<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('submission_answers', 'earned_points')) {
                $table->decimal('earned_points', 8, 2)->nullable();
            }

            if (! Schema::hasColumn('submission_answers', 'feedback')) {
                $table->text('feedback')->nullable();
            }

            if (! Schema::hasColumn('submission_answers', 'checked_by')) {
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('submission_answers', 'checked_at')) {
                $table->timestamp('checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('submission_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('submission_answers', 'checked_by')) {
                $table->dropConstrainedForeignId('checked_by');
            }

            foreach (['earned_points', 'feedback', 'checked_at'] as $column) {
                if (Schema::hasColumn('submission_answers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
