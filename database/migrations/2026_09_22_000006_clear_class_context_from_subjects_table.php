<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [];

        if (Schema::hasColumn('subjects', 'year_level')) {
            $updates['year_level'] = null;
        }

        if (Schema::hasColumn('subjects', 'semester_id')) {
            $updates['semester_id'] = null;
        }

        if (! empty($updates)) {
            DB::table('subjects')->update($updates);
        }
    }

    public function down(): void
    {
        //
    }
};
