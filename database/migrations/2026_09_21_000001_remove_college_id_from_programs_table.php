<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('programs', 'college_id')) {
            return;
        }

        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->dropUnique('programs_college_id_program_name_unique');
            });
        } catch (Throwable) {
            // Index may already be absent after previous normalization.
        }

        Schema::table('programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('college_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table): void {
            $table->foreignId('college_id')
                ->nullable()
                ->after('program_id')
                ->constrained('colleges', 'college_id')
                ->nullOnDelete();
        });

        DB::table('programs')
            ->join('departments', 'programs.department_id', '=', 'departments.department_id')
            ->update(['programs.college_id' => DB::raw('departments.college_id')]);

        try {
            Schema::table('programs', function (Blueprint $table): void {
                $table->unique(['college_id', 'program_name']);
            });
        } catch (Throwable) {
            // Index may already exist on a partially rolled-back database.
        }
    }
};
