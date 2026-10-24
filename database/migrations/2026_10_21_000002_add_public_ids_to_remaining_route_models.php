<?php

use App\Services\RoutePublicIds;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (RoutePublicIds::TABLES as $name => $primaryKey) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'public_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->uuid('public_id')->nullable());
            }
        }

        app(RoutePublicIds::class)->fillMissing();

        foreach (RoutePublicIds::TABLES as $name => $primaryKey) {
            if (Schema::hasTable($name)) {
                app(RoutePublicIds::class)->ensureUniqueIndex($name);
            }
        }
    }

    public function down(): void
    {
        // Retain published URL identifiers and existing record data on rollback.
    }
};
