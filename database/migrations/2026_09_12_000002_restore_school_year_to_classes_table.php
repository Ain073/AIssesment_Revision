<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'school_year')) {
                $table->string('school_year', 20)->nullable()->after('section_name');
            }
        });

        if (Schema::hasColumn('classes', 'school_year')) {
            DB::table('classes')
                ->where(function ($query): void {
                    $query->whereNull('school_year')
                        ->orWhere('school_year', '');
                })
                ->update([
                    'school_year' => $this->currentAcademicYear(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'school_year')) {
                $table->dropColumn('school_year');
            }
        });
    }

    private function currentAcademicYear(): string
    {
        $now = now();
        $startYear = (int) $now->format('n') >= 6
            ? (int) $now->format('Y')
            : (int) $now->format('Y') - 1;

        return $startYear.'-'.($startYear + 1);
    }
};
