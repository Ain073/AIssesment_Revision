<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('classes')
            ->where('section_name', 'like', 'BSBAMM%')
            ->get()
            ->each(function ($class) {
                $newSection = preg_replace('/^BSBAMM/i', 'BSBA MM', $class->section_name);
                DB::table('classes')
                    ->where('class_id', $class->class_id)
                    ->update([
                        'section_name' => $newSection,
                    ]);
            });
    }

    public function down(): void
    {
        // No reversal needed for data normalization
    }
};
